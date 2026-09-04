import DOMPurify from 'dompurify'
import { marked } from 'marked'

marked.setOptions({ breaks: true, gfm: true })

/**
 * Render LLM answer text (Markdown) to sanitized HTML for v-html. Assistant
 * answers routinely come back with #/## headings, **bold**, and lists —
 * rendering them (instead of showing the raw markdown) is what this is for.
 */
export function renderMarkdown(text: string): string {
  const html = marked.parse(text, { async: false })
  return DOMPurify.sanitize(html)
}
