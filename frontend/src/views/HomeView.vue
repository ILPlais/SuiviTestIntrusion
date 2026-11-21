<!--
	Accueil d'un utilisateur connecté (identite-1) : salutation et état de
	l'instance. La liste des tests viendra avec engagement-2.
-->
<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import HealthStatus from '../components/HealthStatus.vue'
import { useAuthStore } from '../stores/auth'

const { t } = useI18n()
const auth = useAuthStore()
</script>

<template>
	<p v-if="auth.user">
		{{ t('auth.welcome', { name: auth.user.name }) }}
	</p>
	<HealthStatus />
</template>
