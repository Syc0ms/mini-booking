import { definePreset } from '@primeuix/themes'
import Aura from '@primeuix/themes/aura'

// Aura with one muted blue accent and warm neutral surfaces, so PrimeVue
// buttons, inputs and dialogs match the page instead of the stock look.
export const RoomPreset = definePreset(Aura, {
  primitive: {
    borderRadius: {
      none: '0',
      xs: '2px',
      sm: '4px',
      md: '6px',
      lg: '8px',
      xl: '10px',
    },
  },
  semantic: {
    primary: {
      50: '#f2f5f9',
      100: '#e1e8f1',
      200: '#c4d2e4',
      300: '#9cb3d0',
      400: '#6f8fb8',
      500: '#4f71a0',
      600: '#3e5c87',
      700: '#344c6f',
      800: '#2c405c',
      900: '#26374d',
      950: '#182333',
    },
    focusRing: {
      width: '2px',
      style: 'solid',
      color: '{primary.500}',
      offset: '2px',
    },
    colorScheme: {
      light: {
        surface: {
          0: '#ffffff',
          50: '#faf8f5',
          100: '#f3f0eb',
          200: '#e6e1d9',
          300: '#d4cdc2',
          400: '#a8a094',
          500: '#7b746a',
          600: '#5d574f',
          700: '#47423c',
          800: '#322f2b',
          900: '#24221f',
          950: '#171614',
        },
        primary: {
          color: '{primary.600}',
          contrastColor: '#ffffff',
          hoverColor: '{primary.700}',
          activeColor: '{primary.800}',
        },
        text: {
          color: '{surface.900}',
          mutedColor: '{surface.600}',
        },
        formField: {
          borderColor: '{surface.300}',
          hoverBorderColor: '{surface.400}',
        },
      },
    },
  },
  components: {
    dialog: {
      root: {
        borderRadius: '{border.radius.lg}',
      },
      header: {
        padding: '1.25rem 1.5rem 0.5rem',
      },
      content: {
        padding: '0 1.5rem 1.25rem',
      },
      footer: {
        padding: '0 1.5rem 1.5rem',
      },
      title: {
        fontSize: '1.125rem',
        fontWeight: '600',
      },
    },
  },
})
