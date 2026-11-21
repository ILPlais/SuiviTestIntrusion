# SuiviTestIntrusion — SPA

Interface Vue 3 + Vite + Pinia + vue-i18n. En V1, l'interface est en français ; toute chaîne affichée passe par `src/i18n/locales/fr.json` ([13.07 Licence, langues et accessibilité](../docs/10-19%20Conception/13%20D%C3%A9cisions%20d'architecture/13.07%20Licence%2C%20langues%20et%20accessibilit%C3%A9.md)).

## Développement

```bash
npm install
npm run dev     # http://localhost:5173, /api relayé vers http://localhost:8000
```

L'API Laravel doit tourner en parallèle (`php artisan serve` dans `backend/`).

## Tests et build

```bash
npm test        # Vitest
npm run build   # contrôle des types (vue-tsc) puis build de production
```

Les dates reçues de l'API sont en UTC (ISO 8601) ; `src/utils/dates.ts` les affiche dans le fuseau du navigateur.
