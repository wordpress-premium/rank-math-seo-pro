/**
 * External Dependencies
 */
import { isEmpty, values } from 'lodash'

/**
 * WordPress Dependencies
 */
import { __ } from '@wordpress/i18n'
import { RawHTML } from '@wordpress/element'

/**
 * Internal Dependencies
 */
import {
	TextControl,
	Button,
	AnalyzerResult,
	ProgressBar,
} from '@rank-math/components'
import { useAnalyzerContext, AnalyzerContextProvider } from '../context'

const CompetitorAnalysis = ( { onTabSelect } ) => {
	const {
		competitorAnalysis,
		competitorUrl,
		updateUrl,
		localAnalysis,
		analysisError,
		startProgress,
		startAnalysis,
	} = useAnalyzerContext()

	const handleStartAnalysis = ( event ) => {
		event.preventDefault()

		startAnalysis()
	}

	return (
		<>
			<div className="rank-math-box">
				<h2>{ __( 'Competitor Analysis', 'rank-math-pro' ) }</h2>

				<p>
					{ __(
						'Enter a site URL to see how it ranks for the same SEO criteria as your site.',
						'rank-math-pro'
					) }
				</p>

				<form onSubmit={ handleStartAnalysis } className="url-form">
					<TextControl
						variant="default"
						placeholder="https://"
						className="rank-math-analyze-url"
						id="competitor_url"
						value={ competitorUrl }
						onChange={ updateUrl }
					/>

					<Button
						type="submit"
						variant="primary"
						id="competitor_url_submit"
						className="rank-math-recheck no-autostart"
						disabled={ ! competitorUrl }
					>
						{ __( 'Start Audit', 'rank-math-pro' ) }
					</Button>
				</form>

				{ values( competitorAnalysis ).length > 1 && values( localAnalysis ).length > 1 && (
					<div className="rank-math-tooltip">
						<Button
							variant="secondary"
							id="rank-math-toggle-side-by-side"
							onClick={ () => onTabSelect( 'side_by_side' ) }
						>
							<em className="dashicons dashicons-columns"></em>
						</Button>

						<span>{ __( 'Side-by-Side Comparison', 'rank-math-pro' ) }</span>
					</div>
				) }
			</div>

			<div className="rank-math-box rank-math-analyzer-result">
				<span className="wp-header-end"></span>

				{ analysisError && <RawHTML>{ analysisError }</RawHTML> }

				{ startProgress ? (
					<ProgressBar />
				) : (
					! isEmpty( competitorAnalysis ) &&
					! analysisError && (
						<AnalyzerResult results={ competitorAnalysis } />
					)
				) }

				<p style={ { textAlign: 'right' } }>
					<em>
						<strong>{ __( 'Note:', 'rank-math-pro' ) }&nbsp;</strong>
						{ __(
							"The total test count is different for the competitor as we don't have access to their database.",
							'rank-math-pro'
						) }
					</em>
				</p>
			</div>
		</>
	)
}

export default ( { onTabSelect } ) => (
	<AnalyzerContextProvider>
		<CompetitorAnalysis onTabSelect={ onTabSelect } />
	</AnalyzerContextProvider>
)
