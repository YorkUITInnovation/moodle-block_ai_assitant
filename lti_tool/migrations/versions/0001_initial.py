"""initial persistence tables

Revision ID: 0001_initial
Revises:
Create Date: 2025-09-26
"""
from __future__ import annotations
from alembic import op
import sqlalchemy as sa

# revision identifiers, used by Alembic.
revision = '0001_initial'
down_revision = None
branch_labels = None
depends_on = None

def upgrade() -> None:
    op.create_table(
        'lti_oidc_states',
        sa.Column('id', sa.Integer(), primary_key=True, autoincrement=True),
        sa.Column('value', sa.String(length=255), nullable=False, unique=True),
        sa.Column('expires_at', sa.Integer(), nullable=False, index=True),
        sa.Column('consumed', sa.Boolean(), nullable=False, server_default=sa.text('0')),
        sa.Column('consumed_at', sa.Integer(), nullable=True),
    )
    op.create_index('ix_lti_oidc_states_value', 'lti_oidc_states', ['value'])
    op.create_index('ix_lti_oidc_states_expires_at', 'lti_oidc_states', ['expires_at'])

    op.create_table(
        'lti_oidc_nonces',
        sa.Column('id', sa.Integer(), primary_key=True, autoincrement=True),
        sa.Column('value', sa.String(length=255), nullable=False, unique=True),
        sa.Column('expires_at', sa.Integer(), nullable=False, index=True),
        sa.Column('consumed', sa.Boolean(), nullable=False, server_default=sa.text('0')),
        sa.Column('consumed_at', sa.Integer(), nullable=True),
    )
    op.create_index('ix_lti_oidc_nonces_value', 'lti_oidc_nonces', ['value'])
    op.create_index('ix_lti_oidc_nonces_expires_at', 'lti_oidc_nonces', ['expires_at'])

    op.create_table(
        'lti_launches',
        sa.Column('id', sa.Integer(), primary_key=True, autoincrement=True),
        sa.Column('issuer', sa.String(length=255), nullable=False, index=True),
        sa.Column('client_id', sa.String(length=255), nullable=False, index=True),
        sa.Column('user_sub', sa.String(length=255), nullable=False, index=True),
        sa.Column('deployment_id', sa.String(length=255), nullable=False, index=True),
        sa.Column('created_at', sa.Integer(), nullable=False, index=True),
        sa.Column('raw_claims', sa.Text(), nullable=False),
    )
    op.create_index('idx_launch_issuer_client', 'lti_launches', ['issuer','client_id'])


def downgrade() -> None:
    op.drop_index('idx_launch_issuer_client', table_name='lti_launches')
    op.drop_table('lti_launches')
    op.drop_index('ix_lti_oidc_nonces_expires_at', table_name='lti_oidc_nonces')
    op.drop_index('ix_lti_oidc_nonces_value', table_name='lti_oidc_nonces')
    op.drop_table('lti_oidc_nonces')
    op.drop_index('ix_lti_oidc_states_expires_at', table_name='lti_oidc_states')
    op.drop_index('ix_lti_oidc_states_value', table_name='lti_oidc_states')
    op.drop_table('lti_oidc_states')

