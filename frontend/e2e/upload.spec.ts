import { expect, test } from '@playwright/test'

/**
 * End-to-end happy path for Phases 1–3. Requires the full stack running
 * (`docker compose up`) so the Vite dev server can proxy `/api` to Laravel and
 * the rag-service can chunk + embed. Phase 3 uses a fake Anthropic key, so it
 * only exercises the request/error path, not a real answer.
 */
test('register, create a project, upload, process, and query a document', async ({ page }) => {
  test.setTimeout(120_000)
  const email = `e2e+${Date.now()}@example.com`

  await page.goto('/register')
  await page.getByLabel('Name', { exact: true }).fill('E2E Tester')
  await page.getByLabel('Email').fill(email)
  await page.getByLabel('Password', { exact: true }).fill('password123')
  await page.getByLabel('Confirm password').fill('password123')
  await page.getByRole('button', { name: 'Create account' }).click()

  await expect(page.getByRole('heading', { name: 'Projects' })).toBeVisible()

  await page.getByPlaceholder('New project name').fill('Handbook')
  await page.getByRole('button', { name: 'Create' }).click()

  await page.getByRole('link', { name: /Handbook/ }).click()
  await expect(page.getByRole('heading', { name: 'Handbook' })).toBeVisible()

  await page.locator('input[type="file"]').setInputFiles({
    name: 'policy.md',
    mimeType: 'text/markdown',
    buffer: Buffer.from(
      '# Travel Policy\n\nBook all travel 14 days in advance through the corporate portal. ' +
        'Economy class is standard for flights under six hours.\n',
    ),
  })

  const row = page.getByTestId('document-row').filter({ hasText: 'policy.md' })
  await expect(row.getByText('Uploaded')).toBeVisible()

  await row.getByRole('button', { name: 'Process' }).click()

  // queued → chunking → ready (rag-service must be up)
  await expect(row.getByText('Ready')).toBeVisible({ timeout: 60_000 })

  await row.getByRole('button', { name: 'policy.md' }).click()
  await expect(page.getByText(/Chunks — policy\.md/)).toBeVisible()

  await expect(page.getByRole('button', { name: 'Export embeddings' })).toBeEnabled()

  // Phase 3: add a key, confirm the Ask tab lights up, and ask a question.
  await page.locator('nav').getByRole('button', { name: 'Ask' }).click()
  await page.getByPlaceholder('sk-ant-…').fill('sk-ant-e2e-fake-key-not-real-000000')
  await page.getByRole('button', { name: 'Save key' }).click()
  await expect(page.getByText('Configured')).toBeVisible()

  await page.getByPlaceholder(/Ask a question/).fill('What is the travel policy?')
  await page.locator('form').getByRole('button', { name: 'Ask' }).click()

  // The fake key is rejected by Anthropic — assert the pipeline reports a
  // clean, specific error rather than crashing or hanging.
  await expect(page.getByText(/Anthropic API key on this project is invalid/)).toBeVisible({ timeout: 30_000 })
})
