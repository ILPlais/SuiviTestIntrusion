<script setup lang="ts">
import { computed, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { useHealthStore } from '../stores/health'
import { formatDateTime } from '../utils/dates'

const { t, locale } = useI18n()
const health = useHealthStore()

const message = computed(() => {
  switch (health.state) {
    case 'ok': return t('health.ok')
    case 'degraded': return t('health.degraded')
    case 'unreachable': return t('health.unreachable')
    default: return t('health.checking')
  }
})

const serverTime = computed(() =>
  health.serverTime ? formatDateTime(health.serverTime, locale.value) : null,
)

onMounted(() => health.check())
</script>

<template>
  <section aria-labelledby="health-heading">
    <h2 id="health-heading">{{ t('health.heading') }}</h2>
    <p role="status" :data-state="health.state">{{ message }}</p>
    <p v-if="serverTime">{{ t('health.serverTime', { time: serverTime }) }}</p>
    <button type="button" :disabled="health.state === 'checking'" @click="health.check()">
      {{ t('health.retry') }}
    </button>
  </section>
</template>
