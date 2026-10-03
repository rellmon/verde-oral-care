import js from '@eslint/js'
import globals from 'globals'
import reactHooks from 'eslint-plugin-react-hooks'
import reactRefresh from 'eslint-plugin-react-refresh'
import { defineConfig, globalIgnores } from 'eslint/config'

export default defineConfig([
  globalIgnores(['dist']),
  {
    files: ['**/*.{js,jsx}'],
    extends: [
      js.configs.recommended,
      reactHooks.configs.flat.recommended,
      reactRefresh.configs.vite,
    ],
    languageOptions: {
      globals: globals.browser,
      parserOptions: { ecmaFeatures: { jsx: true } },
    },
        languageOptions: {
      globals: globals.browser,
      parserOptions: { ecmaFeatures: { jsx: true } },
    },
    rules: {
      // Our pages load data in an effect: set "loading", then fetch. React now warns about this
      // pattern and suggests a data-fetching library (TanStack Query or router loaders) instead.
      // We keep the simple pattern on purpose; it costs one extra render per request.
      // Every other react-hooks rule (rules-of-hooks, exhaustive-deps) stays on.
      'react-hooks/set-state-in-effect': 'off',
    },
  },
])
