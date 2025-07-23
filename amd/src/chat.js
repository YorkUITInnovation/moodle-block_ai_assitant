import {get_string as getString} from 'core/str';
import ajax from 'core/ajax';

export const sendMessage = async () => {
  const input = document.getElementById('block-ai-assistant-chat-input');
  const prompt = input.value;
  if (prompt) {
    const chatMessages = document.getElementById('chat-messages');
    const loadingText = await getString('loading', 'block_learningassist');

    const html = `<div class="chat-message human-message">
                        <div class="message-content">${prompt}</div>
                     </div>
                    <div id="block-ai-assistant-delete-me" class="chat-message bot-message">
                        <div class="message-content">
                        <i class="fa fa-spinner fa-pulse fa-3x fa-fw"></i>
                        <span class="sr-only">${loadingText}</span></div>
                     </div>`;
    // Scroll down to the top of new message
    chatMessages.innerHTML += html;
    chatMessages.scrollTop = chatMessages.scrollHeight;
    input.value = '';

    const courseId = document.getElementById('block-ai-assistant-courseid').value;
    const chatId = document.getElementById('block-ai-assistant-chatid').value;
    const botName = document.getElementById('block-ai-assistant-botname').value;

    if (prompt) {
      const response = await ajax.call([{
        methodname: 'block_ai_assistant_chat',
        args: {
          courseid: courseId,
          botname: botName,
          prompt: prompt,
          chatid: chatId
        },
      }]);
      response[0].done((result) => {
        const deleteMe = document.getElementById('block-ai-assistant-delete-me');
        if (deleteMe) {
          deleteMe.remove();
        }
        const bot_html = `<div class="chat-message bot-message">
                            <div class="message-content">${result}</div>
                         </div>`;
        chatMessages.innerHTML += bot_html;
        input.focus();
      }).fail((error) => {
        console.log(error);
      });
    }
  }
};

document.getElementById('block-ai-assistant-chat-send-btn').addEventListener('click', sendMessage);

document.getElementById('block-ai-assistant-chat-input').addEventListener('keydown', (e) => {
  if (e.key === 'Enter') {
    e.preventDefault();
    sendMessage();
  }
});
