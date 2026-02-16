import { check, sleep } from 'k6'
import http from 'k6/http'
import { apiBaseUrl, buildOptions, resolveListingId } from './helpers.js'

export const options = buildOptions('listing_detail')

export function setup() {
  return { listingId: resolveListingId() }
}

export default function (data) {
  const response = http.get(`${apiBaseUrl}/listings/${data.listingId}`, { tags: { step: 'detail' } })

  check(response, {
    'detail status is 200': (r) => r.status === 200,
    'detail contains listing id': (r) => String(r.json('data.id') || r.json('id')) === String(data.listingId),
  })

  sleep(1)
}
