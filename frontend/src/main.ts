import { createApp } from 'vue'
import { createPinia, setActivePinia } from 'pinia'
import App from './App.vue'
import router from './router'
import './assets/styles.css'

import { useThemeStore } from '@/store/theme'
import { useAuthStore } from '@/store/auth'
import api from '@/services/api'

const app = createApp(App)

const pinia = createPinia()
app.use(pinia)
setActivePinia(pinia)

// Apply persisted theme immediately before any rendering
const themeStore = useThemeStore()
themeStore.applyTheme()

// Restore session user/permissions if a token is present
const authStore = useAuthStore()
if (authStore.token && !authStore.user) {
	api
		.get('/auth/me')
		.then((res) => {
			const data: any = res.data
			const user = data?.data?.user ?? data?.user
			const permissions = data?.data?.permissions ?? data?.permissions
			if (user) authStore.setUser(user)
			authStore.setPermissions(Array.isArray(permissions) ? permissions : [])
		})
		.catch(() => {
			authStore.logout()
		})
}

app.use(router)

app.mount('#app')
