/**
 * WordPress Dependencies
 */
import { createContext, useState } from '@wordpress/element'
import { withSelect, withDispatch } from '@wordpress/data'
import { compose } from '@wordpress/compose'

/**
 * Internal Dependencies
 */
import ajax from '@helpers/ajax'

export const AnalyzerContext = createContext()

const AnalyzerContextProvider = ( { children, startAudit, ...remainingProps } ) => {
	const [ startProgress, setStartProgress ] = useState( false )
	const [ analysisError, setAnalysisError ] = useState( '' )

	/**
	 * Start SEO Analysis
	 */
	const startAnalysis = () => {
		startAudit( setStartProgress, setAnalysisError )
	}

	return (
		<AnalyzerContext.Provider
			value={ {
				...remainingProps,
				startProgress,
				setStartProgress,
				startAnalysis,
				analysisError,
			} }
		>
			{ children }
		</AnalyzerContext.Provider>
	)
}

export default compose(
	withSelect( ( select ) => {
		return {
			competitorAnalysis: select( 'rank-math-pro-seo-analysis' ).getCompetitorResults(),
			competitorUrl: select( 'rank-math-pro-seo-analysis' ).getCompetitorUrl(),
			localAnalysis: select( 'rank-math-seo-analysis' ).getResults(),
			localUrl: select( 'rank-math-seo-analysis' ).getUrl(),
		}
	} ),
	withDispatch( ( dispatch, props ) => {
		return {
			/**
			 * Updates the URL based on the value entered.
			 *
			 * @param {string} url - The new URL value.
			 */
			updateUrl( url ) {
				dispatch( 'rank-math-pro-seo-analysis' ).updateCompetitorUrl( url )
			},
			/**
			 * Perform an SEO audit for the given URL.
			 *
			 * @param {Function} setStartProgress Function to update the state indicating the progress of the request.
			 * @param {Function} setAnalysisError Function to update the state with any error received from the request.
			 */
			startAudit( setStartProgress, setAnalysisError ) {
				setStartProgress( true )

				ajax(
					'analyze',
					{
						u: props.competitorUrl,
						competitor_analyzer: true,
					}
				).always( ( response ) => {
					if ( response.error ) {
						setAnalysisError( response.error )
					} else {
						dispatch( 'rank-math-pro-seo-analysis' ).updateCompetitorResults( response )
					}
					setStartProgress( false )
				} )
			},
		}
	} )
)( AnalyzerContextProvider )
