import { check, sleep } from 'k6'
import http from 'k6/http'
import { apiBaseUrl, buildOptions } from './helpers.js'

export const options = buildOptions('search_listings')

export default function () {
  const city = __ENV.SEARCH_CITY || ''
  const query = city ? `?city=${encodeURIComponent(city)}&perPage=20` : '?perPage=20'
  const response = http.get(`${apiBaseUrl}/search/listings${query}`, { tags: { step: 'search' } })

  check(response, {
    'search status is 200': (r) => r.status === 200,
    'search payload has data array': (r) => Array.isArray(r.json('data')),
  })

  sleep(1)
}
