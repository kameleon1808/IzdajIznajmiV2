import { check, sleep } from 'k6'
import http from 'k6/http'
import { apiBaseUrl, authJsonHeaders, buildOptions, loginWithSession } from './helpers.js'

export const options = buildOptions('saved_search_create')

export default function () {
  const jar = http.cookieJar()
  const loginResponse = loginWithSession(jar)
  check(loginResponse, { 'login status is 200': (r) => r.status === 200 })

  const filters = {
    city: `k6-${__VU}-${__ITER}`,
    rooms: 1,
  }
  const payload = JSON.stringify({
    name: `k6-saved-search-${__VU}-${__ITER}`,
    filters,
    alerts_enabled: false,
    frequency: 'instant',
  })

  const createResponse = http.post(
    `${apiBaseUrl}/saved-searches`,
    payload,
    {
      jar,
      headers: authJsonHeaders(jar),
      tags: { step: 'saved_search_create' },
    },
  )

  check(createResponse, {
    'saved search create status is 201': (r) => r.status === 201,
  })

  const createdId = createResponse.json('data.id') || createResponse.json('id')
  if (createdId) {
    const deleteResponse = http.del(
      `${apiBaseUrl}/saved-searches/${createdId}`,
      null,
      {
        jar,
        headers: authJsonHeaders(jar),
        tags: { step: 'saved_search_delete' },
      },
    )
    check(deleteResponse, {
      'saved search delete status is 200': (r) => r.status === 200,
    })
  }

  sleep(1)
}
