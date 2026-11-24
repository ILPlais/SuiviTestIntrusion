/**
 * Couleurs des badges de statut et de sévérité (socle-9, maquettes de 15.01).
 */
import { describe, expect, it } from 'vitest'
import { severityTag, statusTag } from '../../src/api/testContext'

describe('severityTag', () => {
	it('suit l\'échelle de 11.10, gris sans cotation', () => {
		expect(['critical', 'major', 'important', 'minor', 'none', null].map((severity) => severityTag(severity as never))).toEqual([
			'tag-red', 'tag-peach', 'tag-yellow', 'tag-green', 'tag-grey', 'tag-grey',
		])
	})
})

describe('statusTag', () => {
	it('distingue les statuts d\'un test', () => {
		expect(['planned', 'in_progress', 'done', 'validated'].map((status) => statusTag(status as never))).toEqual([
			'tag-grey', 'tag-blue', 'tag-green', 'tag-green',
		])
	})
})
