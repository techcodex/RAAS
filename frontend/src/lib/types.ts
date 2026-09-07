export interface Organization {
  id: number
  name: string
  slug: string
  role?: string
}

export interface User {
  id: number
  name: string
  email: string
  is_admin: boolean
  current_organization: Organization | null
  created_at: string
}

export type OrganizationStatus = 'active' | 'disabled'

export interface AdminOrganization {
  id: number
  name: string
  slug: string
  status: OrganizationStatus
  document_limit: number | null
  effective_document_limit: number | null
  members_count: number
  projects_count: number
  documents_count: number
  owner: { id: number | null; name: string | null; email: string | null }
  created_at: string
  updated_at: string
}

export interface AdminStats {
  organizations: { total: number; active: number; disabled: number }
  projects: { total: number }
  documents: { total: number; by_status: Record<DocumentStatus, number> }
}

export interface AdminSettings {
  documents: { max_size_kb: number; allowed_extensions: string[] }
  organizations: { default_document_limit: number | null }
}

export type DocumentStatus =
  | 'uploaded'
  | 'queued'
  | 'chunking'
  | 'embedding'
  | 'ready'
  | 'failed'

export type ReembedStatus = 'queued' | 'running' | 'failed'

export interface ProjectEmbedder {
  provider: string | null
  model: string | null
  bound_model_id: string | null
  dimension: number | null
  reembed_status: ReembedStatus | null
  reembed_error: string | null
}

export interface Project {
  id: number
  name: string
  description: string | null
  documents_count?: number
  chunking_strategy: string | null
  chunking_config: Record<string, unknown> | null
  embedder: ProjectEmbedder
  created_at: string
  updated_at: string
}

export interface DocumentFile {
  id: number
  project_id: number
  original_filename: string
  mime_type: string
  size_bytes: number
  status: DocumentStatus
  error_message: string | null
  chunking_strategy: string | null
  chunk_count: number
  processed_at: string | null
  uploaded_by: Pick<User, 'id' | 'name' | 'email'> | null
  created_at: string
  updated_at: string
}

export interface Chunk {
  id: number
  document_id: number
  chunk_index: number
  content: string
  token_count: number
  metadata: Record<string, unknown>
}

export interface ChunkingStrategy {
  name: string
  label: string
  description: string
  config_schema: JsonSchema
  defaults: Record<string, unknown>
}

export interface JsonSchema {
  type?: string
  properties?: Record<string, JsonSchemaProperty>
}

export interface JsonSchemaProperty {
  type?: string
  title?: string
  description?: string
  default?: unknown
  minimum?: number
  maximum?: number
  enum?: unknown[]
}

export interface StrategyCatalogue {
  strategies: ChunkingStrategy[]
  embedders: {
    provider: string
    label: string
    description: string
    models: { id: string; dimension: number }[]
    default_model: string
  }[]
}

export interface Paginated<T> {
  data: T[]
  meta: { current_page: number; last_page: number; per_page: number; total: number }
}

export interface ProjectCredentialInfo {
  provider: string
  model: string
  configured: true
  created_at: string
  updated_at: string
}

export interface Citation {
  document_id: number
  chunk_index: number
  score: number
  excerpt: string
}

export interface LlmUsage {
  model: string
  stop_reason: string | null
  input_tokens: number
  output_tokens: number
}

export interface ChatMessage {
  id: number
  conversation_id: number
  role: 'user' | 'assistant'
  content: string
  citations: Citation[]
  usage: LlmUsage | null
  created_at: string
}

export interface Conversation {
  id: number
  project_id: number
  title: string | null
  messages?: ChatMessage[]
  created_at: string
  updated_at: string
}
