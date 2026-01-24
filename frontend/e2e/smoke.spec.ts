import { test, expect } from '@playwright/test'

test.describe('Smoke navigation', () => {
  test.beforeEach(async ({ page }) => {
    await page.addInitScript(() => {
      window.localStorage.setItem(
        'ii-auth-state',
        JSON.stringify({
          user: { id: 'smoke', name: 'Smoke Tester', role: 'seeker', roles: ['seeker'] },
          impersonating: false,
          impersonator: null,
        }),
      )
    })
  })

  test('home to search to listing to messages', async ({ page }) => {
    await page.goto('/')
    await expect(page.getByRole('heading', { name: 'Most Popular' })).toBeVisible()

    await page.getByRole('button', { name: 'See all' }).first().click()
    await expect(page).toHaveURL(/\/search/)
    await expect(page.getByRole('heading', { name: 'Results' })).toBeVisible()

    const firstCard = page.getByTestId('listing-card-horizontal').first()
    const titleText = (await firstCard.getByRole('heading').textContent())?.trim() || /.+/
    await firstCard.click()
    await expect(page).toHaveURL(/\/listing\/\d+/)
    await expect(page.getByRole('heading', { level: 1, name: titleText })).toBeVisible()

    await page.goto('/messages')
    await expect(page).toHaveURL(/\/messages/)
    await expect(page.getByPlaceholder('Search messages')).toBeVisible()
  })
})
