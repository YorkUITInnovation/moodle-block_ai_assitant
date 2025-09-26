from __future__ import annotations
"""
CriaClient
----------
Python/FastAPI translation scaffold of the legacy Moodle PHP class `cria`.
This isolates CRIA (bot + content + chat) operations behind a service class so they
can later be wired to LTI launches (course context) and extended with real persistence.

NOTES / PARITY WITH PHP IMPLEMENTATION
=====================================
Ported (logic structure mirrored, external calls abstracted):
 - create_bot_instance / update_bot_instance / delete_bot_instance
 - get_bot_name_intent_id
 - get_availability
 - upload_content_to_bot / delete_content_from_bot / get_content_training_status
 - create_small_talk_questions (simplified: returns prepared payload list; actual create/publish loop executes remote calls)
 - get_embed_bot_code
 - create_question / delete_question / publish_question
 - create_questions_from_json
 - chat_exists / chat_start / chat_send / chat_history / chat_end
 - get_gpt_response
 - get_api_key / start_session

Deferred / Placeholder (Moodle‑dependent) functions:
 - get_create_cria_bot_config (Moodle DB/config replaced with env + supplied params)
 - get_default_system_message / get_default_no_context_message (use env templates)
 - get_teacher_emails / get_teachers (requires LMS roster; placeholder returns empty list)
 - copy_file_to_temp_folder / create_questions_from_xlsx / run_autotest (require Moodle FS & CLI)
 - split_bot_name / get_bot_id / get_intent_id (managed through BotRegistry instead)

Environment Variables (override defaults):
 - CRIA_BASE_URL (default: http://localhost:9000)
 - CRIA_API_TOKEN (optional bearer token)
 - CRIA_EMBED_URL (for embed script generation)
 - CRIA_DEFAULT_SYSTEM_MESSAGE (template, supports [course_number] [course_title])
 - CRIA_DEFAULT_NO_CONTEXT_MESSAGE
 - CRIA_BOT_TITLE (name used in small talk answers)

Design Choices:
 - Synchronous client using httpx (can be refactored to async if desired).
 - Central _exec() method emulates PHP webservice::exec pattern: POST /rpc with JSON {method, params}.
 - Light in‑memory BotRegistry to store per-course bot metadata until DB schema added.
 - Returns Python dicts / dataclasses, leaving HTML generation & LMS wiring to higher layers.

TODO (future iterations):
 - Replace in‑memory registry with DB table + Alembic migration (cria_bots).
 - Add proper error mapping & retries.
 - Integrate syllabus parsing & file conversion pipeline.
 - Align question creation with spreadsheet ingest.

This file is intentionally self‑contained for initial integration.
"""
from dataclasses import dataclass
from typing import Any, Dict, List, Optional
import os
import time
import base64
import httpx

# ---------------------------------------------------------------------------
# Data Models
# ---------------------------------------------------------------------------
@dataclass
class BotInfo:
    course_id: str | int
    bot_id: str
    intent_id: str
    bot_name: str  # original concatenated name if needed
    created_at: int
    updated_at: int

@dataclass
class ChatSendResult:
    status: int
    content: str | None = None
    code: str | None = None

@dataclass
class TrainingStatus:
    training_status_id: int
    training_status: str  # simplified textual status (badge HTML removed)

# ---------------------------------------------------------------------------
# In-memory Bot Registry (placeholder until DB persistence)
# ---------------------------------------------------------------------------
class BotRegistry:
    def __init__(self) -> None:
        self._store: Dict[str | int, BotInfo] = {}

    def set_bot(self, course_id: str | int, bot_id: str, intent_id: str) -> BotInfo:
        bot_name = f"{bot_id}-{intent_id}"
        now = int(time.time())
        info = BotInfo(course_id=course_id, bot_id=bot_id, intent_id=intent_id, bot_name=bot_name, created_at=now, updated_at=now)
        self._store[course_id] = info
        return info

    def get(self, course_id: str | int) -> Optional[BotInfo]:
        return self._store.get(course_id)

    def bot_id(self, course_id: str | int) -> Optional[str]:
        bi = self.get(course_id)
        return bi.bot_id if bi else None

    def intent_id(self, course_id: str | int) -> Optional[str]:
        bi = self.get(course_id)
        return bi.intent_id if bi else None

# Single global instance for now
bot_registry = BotRegistry()

# ---------------------------------------------------------------------------
# Exceptions
# ---------------------------------------------------------------------------
class CriaError(Exception):
    pass

class CriaRemoteError(CriaError):
    def __init__(self, method: str, status_code: int, message: str):
        super().__init__(f"CRIA method '{method}' failed ({status_code}): {message}")
        self.method = method
        self.status_code = status_code
        self.message = message

# ---------------------------------------------------------------------------
# Client
# ---------------------------------------------------------------------------
class CriaClient:
    def __init__(self, base_url: str | None = None, api_token: str | None = None, timeout: float = 15.0) -> None:
        self.base_url = base_url or os.getenv("CRIA_BASE_URL", "http://localhost:9000")
        self.api_token = api_token or os.getenv("CRIA_API_TOKEN")
        self.timeout = timeout
        self._http = httpx.Client(timeout=timeout)

    # --- Core RPC Helper --------------------------------------------------
    def _exec(self, method: str, params: Dict[str, Any]) -> Any:
        # Generic JSON-RPC-ish POST (adjust to real CRIA API shape when known)
        url = f"{self.base_url}/rpc"
        headers = {"Content-Type": "application/json"}
        if self.api_token:
            headers["Authorization"] = f"Bearer {self.api_token}"
        payload = {"method": method, "params": params}
        try:
            resp = self._http.post(url, json=payload)
        except httpx.HTTPError as e:
            raise CriaError(f"Network error calling {method}: {e}") from e
        if resp.status_code >= 400:
            raise CriaRemoteError(method, resp.status_code, resp.text)
        try:
            data = resp.json()
        except ValueError:
            # Fall back to raw text
            data = resp.text
        return data

    # --- Bot Management ---------------------------------------------------
    def create_bot_instance(self, course_id: int | str) -> str:
        cfg = self._build_bot_config(course_id, is_syllabus=True)
        bot_resp = self._exec("cria_create_bot", cfg)
        # Expecting something like bot_id or bot_name string -> simplify
        bot_name = self._sanitize_quotes(str(bot_resp))
        # Retrieve name-intent if available
        pair = self.get_bot_name_intent_id(bot_name)
        if isinstance(pair, str) and '-' in pair:
            bot_id, intent_id = pair.split('-', 1)
        else:
            # Fallback: assume bot_resp is id, need second fetch? Keep placeholder
            bot_id = bot_name
            intent_id = "0"
        bot_registry.set_bot(course_id, bot_id, intent_id)
        return bot_name

    def update_bot_instance(self, course_id: int | str, bot_id: str) -> str:
        cfg = self._build_bot_config(course_id, is_syllabus=True)
        cfg['id'] = bot_id
        updated = self._exec("cria_create_bot", cfg)
        return str(updated)

    def delete_bot_instance(self, bot_id: str) -> str:
        return str(self._exec("cria_bot_delete", {"id": bot_id}))

    def get_bot_name_intent_id(self, bot_name: str) -> Any:
        return self._exec("cria_get_bot_name", {"bot_id": bot_name})

    def get_availability(self) -> Dict[str, Any]:
        raw = self._exec("cria_get_availability", {})
        # Mirror PHP logic (array vs object variability)
        if isinstance(raw, list) and raw:
            first = raw[0]
            return {
                'exception': first.get('exception', 'error'),
                'errorcode': first.get('errorcode', 'unknown'),
                'message': first.get('message', 'Unknown status'),
            }
        if isinstance(raw, dict):
            return raw
        return {
            'exception': 'error',
            'errorcode': '404: Site unavailable',
            'message': 'Cria is currently unreachable. Please try again later.'
        }

    # --- Content Operations ----------------------------------------------
    def upload_content_to_bot(self, course_id: int | str, file_name: str, file_content_b64: str, parsing_strategy: str = "") -> Any:
        intent_id = bot_registry.intent_id(course_id)
        if not intent_id:
            raise CriaError("Intent ID not set for course; create bot first.")
        payload = {
            "intentid": int(intent_id),
            "filename": file_name,
            "filecontent": file_content_b64,
            "parsingstrategy": parsing_strategy,
        }
        return self._exec("cria_content_upload", payload)

    def delete_content_from_bot(self, content_id: str | int) -> Any:
        return self._exec("cria_content_delete", {"id": content_id})

    def get_content_training_status(self, content_id: str | int) -> TrainingStatus:
        status = self._exec("cria_content_get_training_status", {"id": content_id})
        # PHP mapping 0..3 -> textual badge names (simplified here)
        mapping = {
            0: (0, "pending"),
            1: (1, "trained"),
            2: (2, "error"),
            3: (3, "training"),
        }
        ts_id, label = mapping.get(int(status), (status, "unknown"))
        return TrainingStatus(training_status_id=ts_id, training_status=label)

    # --- Questions --------------------------------------------------------
    def create_question(self, question_obj: Dict[str, Any]) -> Any:
        return self._exec("cria_question_create", question_obj)

    def delete_question(self, question_id: str | int) -> Any:
        return self._exec("cria_question_delete", {"id": question_id})

    def publish_question(self, question_id: str | int) -> Any:
        return self._exec("cria_question_publish", {"id": question_id})

    def create_small_talk_questions(self, course_id: int | str) -> List[Dict[str, Any]]:
        intent_id = bot_registry.intent_id(course_id)
        if not intent_id:
            raise CriaError("Intent ID not set for course; create bot first.")
        title = os.getenv("CRIA_BOT_TITLE", "AI Assistant")
        # Mirror PHP array with reduced duplication
        templates = [
            ("Small talk - Hello", "Hello", "Hello! How can I help you today?", ["Hi","Hey","Greetings","Good morning","Good afternoon","Good evening"]),
            ("Smalltalk - How are you?", "How are you?", "I am an AI assistant. How can I help you today?", ["How are you?","How are you doing?","How do you do?","How are you feeling?","How are you today?"]),
            ("Small talk - Good bye", "Good bye", "Good bye! Have a great day! If you need help, feel free to ask.", ["Bye","Goodbye","See you later","See you soon","Take care"]),
            ("Small talk - Thank you", "Thank you", "You are welcome! If you need help, feel free to ask.", ["Thanks","Thank you very much","Thank you so much","Gracias","Merci"]),
            ("Small talk - Who are you?", "Who are you?", "I am an AI assistant. How can I help you today?", ["Who are you?","What are you?","What is your name?","What do you do?","What can you do?"]),
            ("Small talk - What is your name?", "What is your name?", f"My name is {title} . How can I help you today?", ["What is your name?","What do you call yourself?","What should I call you?","What are you called?","What is your title?"]),
        ]
        created: List[Dict[str, Any]] = []
        for name, value, answer, examples in templates:
            q_obj = {
                'intentid': int(intent_id),
                'name': name,
                'value': value,
                'answer': answer,
                'relatedquestions': '[]',
                'lang': 'en',
                'generateanswer': 1,
                'examplequestions': self._json_examples(examples),
            }
            q_id = self.create_question(q_obj)
            pub_status = self.publish_question(q_id)
            created.append({'question_id': q_id, 'published': pub_status == 1})
        return created

    def create_questions_from_json(self, json_question_obj: Dict[str, Any], course_id: int | str) -> List[Dict[str, Any]]:
        intent_id = bot_registry.intent_id(course_id)
        if not intent_id:
            raise CriaError("Intent ID not set for course; create bot first.")
        results: List[Dict[str, Any]] = []
        for key, qdata in json_question_obj.items():
            examples = qdata.get('examples', [])
            q_obj = {
                'intentid': int(intent_id),
                'name': key,
                'value': qdata.get('question'),
                'answer': qdata.get('answer'),
                'relatedquestions': '[]',
                'lang': 'en',
                'generateanswer': 0,
                'examplequestions': self._json_examples(examples),
            }
            q_id = self.create_question(q_obj)
            status = self.publish_question(q_id)
            results.append({'name': key, 'question_id': q_id, 'published': bool(status)})
        return results

    # --- Chat -------------------------------------------------------------
    def chat_exists(self, chat_id: str) -> Any:
        return self._exec("cria_chat_exists", {'chat_id': chat_id.strip()})

    def chat_start(self) -> Any:
        return self._exec("cria_chat_start", {})

    def chat_send(self, chat_id: str, prompt: str, bot_name: str) -> ChatSendResult:
        raw = self._exec("cria_chat_send", {'bot_name': bot_name, 'chat_id': chat_id.strip(), 'prompt': prompt})
        if isinstance(raw, dict):
            status = int(raw.get('status', 0))
            if status == 200:
                return ChatSendResult(status=200, content=raw.get('content'))
            return ChatSendResult(status=status, code=str(raw.get('code')), content=raw.get('content'))
        return ChatSendResult(status=0, content=str(raw))

    def chat_history(self, chat_id: str) -> Any:
        return self._exec("cria_chat_history", {'chat_id': chat_id.strip()})

    def chat_end(self, chat_id: str) -> Any:
        return self._exec("cria_chat_end", {'chat_id': chat_id.strip()})

    def get_gpt_response(self, chat_id: str, bot_id: str | int, prompt: str, content: str = "") -> Any:
        return self._exec("cria_get_gpt_response", {
            'bot_id': int(bot_id),
            'chat_id': chat_id.strip().replace('"', ''),
            'prompt': prompt,
            'content': content
        })

    # --- API Key & Session -----------------------------------------------
    def get_api_key(self, bot_id: str | int) -> str:
        resp = self._exec("cria_get_bot_api_key", {'bot_id': int(bot_id)})
        if isinstance(resp, str):
            return self._sanitize_quotes(resp)
        return str(resp)

    def start_session(self, course_id: int | str, api_key: str, payload: Dict[str, Any] | None = None) -> Any:
        bot_id = bot_registry.bot_id(course_id)
        if not bot_id:
            raise CriaError("Bot ID not set for course; create bot first.")
        # Emulate webservice::exec_embed – assuming different endpoint /embed
        url = f"{self.base_url}/embed/session"
        headers = {"Content-Type": "application/json", "Authorization": f"Bearer {api_key}"}
        body = {
            'bot_id': int(bot_id),
            'payload': payload or {},
        }
        try:
            r = self._http.post(url, json=body, headers=headers)
            r.raise_for_status()
            return r.json()
        except httpx.HTTPError as e:
            raise CriaError(f"Embed session start failed: {e}") from e

    # --- Embed Script ----------------------------------------------------
    def get_embed_bot_code(self, bot_name: str) -> str:
        embed_base = os.getenv("CRIA_EMBED_URL")
        if not embed_base:
            return ""  # feature disabled if not configured
        return f"<script type=\"text/javascript\" src=\"{embed_base}/embed/{bot_name}/load\" async></script>"

    # --- Helper / Config Builders ---------------------------------------
    def _build_bot_config(self, course_id: int | str, is_syllabus: bool = True) -> Dict[str, Any]:
        # Placeholder for Moodle-driven config. Pulling values from env for now.
        system_template = os.getenv("CRIA_DEFAULT_SYSTEM_MESSAGE", "Course [course_number]: [course_title]")
        no_context_message = os.getenv("CRIA_DEFAULT_NO_CONTEXT_MESSAGE", "No context available")
        # Replace placeholders (caller should supply actual values later via course metadata service)
        system_message = system_template.replace('[course_number]', str(course_id)).replace('[course_title]', f"Course {course_id}")
        name = f"SITE-{course_id}"  # mimic site shortname + course id mapping
        # Basic config skeleton aligning with legacy fields
        return {
            'name': name,
            'description': os.getenv('CRIA_BOT_DESCRIPTION', 'Course assistant bot'),
            'bot_type': os.getenv('CRIA_BOT_TYPE', 'COURSE'),
            'bot_system_message': system_message,
            'model_id': os.getenv('CRIA_MODEL_ID', 'default-model'),
            'embedding_id': os.getenv('CRIA_EMBEDDING_ID', 'default-embed'),
            'rerank_model_id': os.getenv('CRIA_RERANK_MODEL_ID', 'default-rerank'),
            'requires_content_prompt': int(os.getenv('CRIA_REQUIRES_CONTENT_PROMPT', '0')),
            'requires_user_prompt': int(os.getenv('CRIA_REQUIRES_USER_PROMPT', '0')),
            'user_prompt': os.getenv('CRIA_USER_PROMPT', ''),
            'welcome_message': os.getenv('CRIA_WELCOME_MESSAGE', 'Hello!'),
            'theme_color': os.getenv('CRIA_THEME_COLOR', '#0055A5'),
            'max_tokens': int(os.getenv('CRIA_MAX_TOKENS', '2048')),
            'temperature': float(os.getenv('CRIA_TEMPERATURE', '0.7')),
            'top_p': float(os.getenv('CRIA_TOP_P', '1.0')),
            'top_k': int(os.getenv('CRIA_TOP_K', '50')),
            'top_n': int(os.getenv('CRIA_TOP_N', '1')),
            'min_k': int(os.getenv('CRIA_MIN_K', '3')),
            'min_relevance': float(os.getenv('CRIA_MIN_RELEVANCE', '0.3')),
            'max_context': int(os.getenv('CRIA_MAX_CONTEXT', '20')),
            'no_context_message': no_context_message,
            'no_context_use_message': int(os.getenv('CRIA_NO_CONTEXT_USE_MESSAGE', '0')),
            'no_context_llm_guess': int(os.getenv('CRIA_NO_CONTEXT_LLM_GUESS', '0')),
            'email': '; '.join(self._get_teacher_emails_placeholder(course_id)),
            'available_child': int(os.getenv('CRIA_AVAILABLE_CHILD', '0')),
            'parse_strategy': os.getenv('CRIA_PARSE_STRATEGY', 'GENERIC'),
            'botwatermark': int(os.getenv('CRIA_WATERMARK', '0')),
            'title': os.getenv('CRIA_BOT_TITLE', 'AI Assistant'),
            'subtitle': os.getenv('CRIA_BOT_SUBTITLE', ''),
            'embed_position': os.getenv('CRIA_EMBED_POSITION', 'bottom-right'),
            'icon_file_name': 'ai_assistant.png',
            'icon_file_content': self._default_logo_b64(),
            'bot_locale': os.getenv('CRIA_BOT_LOCALE', 'en'),
            'child_bots': os.getenv('CRIA_CHILD_BOTS', ''),
            'publish': int(os.getenv('CRIA_PUBLISH_ON_CREATE', '0')),
            'bot_contact': os.getenv('CRIA_BOT_CONTACT', ''),
            'bot_help_text': os.getenv('CRIA_BOT_HELP_TEXT', ''),
            'variables': "idNumber\nname\nip\ngrade\ngroups",
            'preprocess_rules': "My id number is [idNumber]\nMy name is [name]\nMy IP address is [ip]\nMy grade is [grade]\nI am in the following groups: [groups]",
        }

    # --- Internal Helpers ------------------------------------------------
    def _sanitize_quotes(self, s: str) -> str:
        return s.replace('"', '')

    def _default_logo_b64(self) -> str:
        # Placeholder: encode empty PNG header or embed base64 of existing ai_assistant.png if accessible.
        logo_path = os.getenv('CRIA_LOGO_PATH')
        if logo_path and os.path.isfile(logo_path):
            with open(logo_path, 'rb') as f:
                return base64.b64encode(f.read()).decode()
        # Fallback to 1x1 transparent PNG
        transparent_png = base64.b64decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8Xw8AAgMBgA0X8q4AAAAASUVORK5CYII=')
        return base64.b64encode(transparent_png).decode()

    def _json_examples(self, examples: List[str]) -> str:
        # Structure matches legacy expectation: list of {value: example}
        import json
        return json.dumps([{'value': e} for e in examples])

    def _get_teacher_emails_placeholder(self, course_id: int | str) -> List[str]:
        # Placeholder until integrated with LMS roster service
        return []

# Convenience factory
_default_client: CriaClient | None = None

def get_cria_client() -> CriaClient:
    global _default_client
    if _default_client is None:
        _default_client = CriaClient()
    return _default_client

