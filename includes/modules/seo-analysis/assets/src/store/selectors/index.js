/**
 * Whether the Content AI Autocompleter is open
 *
 * @param {Object} state The app state.
 *
 * @return {boolean} Return boolean true if the autocompleter is open.
 */
export function getCompetitorResults( state ) {
	return state.appUi.competitorResults
}

/**
 * Whether the Content AI Autocompleter is open
 *
 * @param {Object} state The app state.
 *
 * @return {boolean} Return boolean true if the autocompleter is open.
 */
export function getCompetitorUrl( state ) {
	return state.appUi.competitorUrl
}
