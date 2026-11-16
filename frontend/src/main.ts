import { createApp } from 'vue'
import PrimeVue from 'primevue/config'
import ToastService from 'primevue/toastservice'

// Manrope, self-hosted via @fontsource (SIL Open Font License 1.1).
// Each file covers Latin and Cyrillic through unicode-range subsets.
import '@fontsource/manrope/400.css'
import '@fontsource/manrope/500.css'
import '@fontsource/manrope/600.css'
import '@fontsource/manrope/700.css'
import './style.css'

import App from './App.vue'
import { RoomPreset } from './theme'

const app = createApp(App)

app.use(PrimeVue, {
  theme: {
    preset: RoomPreset,
    options: {
      // The page has a single light theme; never switch components to dark.
      darkModeSelector: '.app-dark',
    },
  },
  locale: {
    aria: {
      close: 'Закрыть',
    },
    emptyMessage: 'Нет вариантов',
  },
})
app.use(ToastService)

app.mount('#app')
