import { check, sleep } from 'k6'
import http from 'k6/http'
import { buildOptions, loginWithSession } from './helpers.js'

export const options = buildOptions('auth_login')

export default function () {
  const jar = http.cookieJar()
  const response = loginWithSession(jar)

  check(response, {
    'login status is 200': (r) => r.status === 200,
  })

  sleep(1)
}
