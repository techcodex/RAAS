import { createPinia } from 'pinia'
import { createApp } from 'vue'

import App from './App.vue'
import { setUnauthorizedHandler } from './lib/api'
import router from './router'
import { useSessionStore } from './stores/session'
import './style.css'

const app = createApp(App)
app.use(createPinia())
app.use(router)

setUnauthorizedHandler(() => {
  useSessionStore().clear()
  const current = router.currentRoute.value
  const target = current.meta.adminArea === true ? 'admin-login' : 'login'
  if (current.name !== target) {
    router.push({ name: target })
  }
})

app.mount('#app')
