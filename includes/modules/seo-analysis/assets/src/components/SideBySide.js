/**
 * External Dependencies
 */
import { map, includes, values } from 'lodash'

/**
 * WordPress Dependencies
 */
import { __ } from '@wordpress/i18n'
import { Fragment, RawHTML, useState } from '@wordpress/element'

/**
 * Internal Dependencies
 */
import {
	Button,
	Graphs,
	getStatusLabels,
	getStatusIcons,
	getCategoryLabel,
} from '@rank-math/components'
import { AnalyzerContextProvider, useAnalyzerContext } from '../context'

/**
 * Generates a graph representation based on the analysis results of a given site or competitor.
 *
 * @param {string} url      URL of the site or competitor.
 * @param {Object} analysis Analysis results for the site or competitor.
 */
const getGraph = ( url, analysis ) => (
	<div className="result-graph">
		<h2>{ url }</h2>
		<Graphs metrices={ analysis.metrices } date={ analysis.date } />
	</div>
)

/**
 * Displays the test result for a given row.
 *
 * @param {Object} test Test result data.
 */
const getTestResult = ( test ) => {
	return (
		<td
			className={ `status-${ test.status }` }
		>
			<span className="status-indicator">
				<span className={ getStatusIcons( test.status ) } />

				{ getStatusLabels( test.status ) }
			</span>

			<RawHTML className="test-result">{ test.message }</RawHTML>
		</td>
	)
}

const SideBySide = ( { onTabSelect } ) => {
	const [ activeItems, setActiveItems ] = useState( [] )
	const {
		competitorUrl,
		competitorAnalysis,
		localAnalysis,
		localUrl,
	} = useAnalyzerContext()

	if (
		values( competitorAnalysis?.results ).length < 1 ||
		values( localAnalysis?.results ).length < 1
	) {
		return null
	}

	return (
		<div className="rank-math-side-by-side">
			<div className="rank-math-box">
				<div className="rank-math-tooltip">
					<Button
						variant="secondary"
						id="rank-math-toggle-side-by-side"
						onClick={ () => ( onTabSelect( 'competitor_analyzer' ) ) }
					>
						<span className="dashicons dashicons-text-page"></span>
					</Button>

					<span>{ __( 'Competitor View', 'rank-math-pro' ) }</span>
				</div>

				<h2>
					{ __( 'Side-by-Side Comparison', 'rank-math-pro' ) }
				</h2>

				<div className="side-by-side-graphs">
					{ getGraph( localUrl, localAnalysis ) }
					{ getGraph( competitorUrl, competitorAnalysis ) }
				</div>

				<div className="rank-math-side-by-side-comparison">
					{
						map( competitorAnalysis.results, ( tests, category ) => {
							const localTests = localAnalysis.results[ category ]
							return (
								<Fragment key={ category }>
									<h3 className="comparison-category-title">
										{ getCategoryLabel( category ) }
									</h3>
									<table className="comparison-table">
										<thead>
											<tr>
												<th>
													{ __( 'Test', 'rank-math-pro' ) }
												</th>
												<th>
													{ __( 'Your Site', 'rank-math-pro' ) }
													<br />
													<small>{ localUrl }</small>
												</th>
												<th>
													{ __( 'Competitor Site', 'rank-math-pro' ) }
													<br />
													<small>{ competitorUrl }</small>
												</th>
											</tr>
										</thead>

										<tbody>
											{ map( tests, ( test, id ) => {
												const localTest = localTests[ id ]
												id = category + '-' + id
												const isActive = includes( activeItems, id )

												return (
													<tr
														onClick={ () => {
															if ( ! isActive ) {
																setActiveItems( [ ...activeItems, id ] )
																return
															}

															setActiveItems( activeItems.filter( ( item ) => item !== id ) )
														} }
														key={ id }
														className={ isActive ? 'is-active' : '' }
													>
														<td>
															<strong className="test-title">
																{ test.title }
															</strong>

															<p className="test-description test-result">
																{ test.tooltip }
															</p>
														</td>

														{ getTestResult( localTest ) }
														{ getTestResult( test ) }
													</tr>
												)
											} ) }
										</tbody>
									</table>
								</Fragment>
							)
						} )
					}
				</div>
			</div>
		</div>
	)
}

export default ( { onTabSelect } ) => (
	<AnalyzerContextProvider>
		<SideBySide onTabSelect={ onTabSelect } />
	</AnalyzerContextProvider>
)
