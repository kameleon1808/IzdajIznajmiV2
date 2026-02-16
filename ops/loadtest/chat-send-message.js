import { check, sleep } from 'k6'
import http from 'k6/http'
import {
  apiBaseUrl,
  authJsonHeaders,
  buildOptions,
  loginWithSession,
  resolveConversationId,
  resolveListingId,
} from './helpers.js'

export const options = buildOptions('chat_send_message')

export function setup() {
  return { listingId: resolveListingId() }
}

export default function (data) {
  const jar = http.cookieJar()
  const loginResponse = loginWithSession(jar)
  check(loginResponse, { 'login status is 200': (r) => r.status === 200 })

  const conversationId = resolveConversationId(jar, data.listingId)

  const fetchResponse = http.get(
    `${apiBaseUrl}/conversations/${conversationId}/messages`,
    {
      jar,
      headers: authJsonHeaders(jar),
      tags: { step: 'chat_open' },
    },
  )
  check(fetchResponse, { 'chat open status is 200': (r) => r.status === 200 })

  const body = JSON.stringify({
    message: `k6 ping vu=${__VU} iter=${__ITER}`,
  })
  const sendResponse = http.post(
    `${apiBaseUrl}/conversations/${conversationId}/messages`,
    body,
    {
      jar,
      headers: authJsonHeaders(jar),
      tags: { step: 'chat_send' },
    },
  )

  check(sendResponse, {
    'chat send status is 201': (r) => r.status === 201,
  })

  sleep(1)
}
