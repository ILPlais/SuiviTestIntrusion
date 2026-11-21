<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import HealthStatus from './components/HealthStatus.vue'

const { t } = useI18n()
</script>

<template>
  <header>
    <h1>{{ t('app.title') }}</h1>
  </header>
  <main>
    <HealthStatus />
  </main>
</template>
