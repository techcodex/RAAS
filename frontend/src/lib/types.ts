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
  current_organization: Organization | null
  created_at: string
}

export type DocumentStatus =
  | 'uploaded'
  | 'queued'
  | 'chunking'
  | 'embedding'
  | 'ready'
  | 'failed'

export interface ProjectEmbedder {
  provider: string | null
  model: string | null
  bound_model_id: string | null
  dimension: number | null
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
