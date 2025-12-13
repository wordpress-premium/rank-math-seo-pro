/**
 * Internal dependencies
 */
import { updateAppUi } from './metadata'

/**
 * Update competitor results.
 *
 * @param {Object} results Competitor Analysis results.
 */
export function updateCompetitorResults( results ) {
	return updateAppUi( 'competitorResults', results )
}

/**
 * Update competitor url.
 *
 * @param {string} url Competitor url.
 */
export function updateCompetitorUrl( url ) {
	return updateAppUi( 'competitorUrl', url )
}
