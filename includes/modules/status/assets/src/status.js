/**
 * External Dependencies
 */
import $ from 'jquery'
import { difference } from 'lodash'

/**
 * WordPress Dependencies
 */
import { addFilter, addAction, removeAllFilters } from '@wordpress/hooks'

/**
 * Internal Dependencies
 */
import CSVFile from './CSVFile'

class RankMathUpdateSEOScoreTool {
	/**
	 * Class constructor
	 */
	constructor() {
		addAction( 'rank_math_pre_update_seo_score', 'rank-math-pro', this.applyContentBoundaries )
		addFilter( 'rank_math_update_score_researches_tests', 'rank-math-pro', this.researchesTests )
		addFilter( 'rank_math_status_import_export_tabs', 'rank-math-pro', ( data, props ) => ( <CSVFile { ...props } /> ) )
	}

	/**
	 * Update SEO Score Content Length boundaries for Products in the Update SEO Score tool.
	 *
	 * @param {Object} data Values used by the Content Analyzer.
	 */
	applyContentBoundaries( data ) {
		addFilter( 'rankMath_analysis_contentLength_boundaries', 'rank-math-pro', () => {
			return {
				recommended: {
					boundary: 200,
					score: 8,
				},
				belowRecommended: {
					boundary: 150,
					score: 5,
				},
				medium: {
					boundary: 120,
					score: 4,
				},
				belowMedium: {
					boundary: 100,
					score: 3,
				},
				low: {
					boundary: 80,
					score: 2,
				},
			}
		}, 11 )

		if ( ! data.isProduct ) {
			removeAllFilters( 'rankMath_analysis_contentLength_boundaries' )
		}
	}

	/**
	 * Update the Content analysis tests for Products.
	 *
	 * @param {Array}  tests Analysis tests.
	 * @param {Object} data  Values used by the Content Analyzer.
	 */
	researchesTests( tests, data ) {
		if ( ! data.isProduct ) {
			return tests
		}

		tests = difference(
			tests,
			[
				'keywordInSubheadings',
				'linksHasExternals',
				'linksNotAllExternals',
				'linksHasInternal',
				'titleSentiment',
				'titleHasNumber',
				'contentHasTOC',
			]
		)

		tests.push( 'hasProductSchema' )
		tests.push( 'isReviewEnabled' )

		return tests
	}
}

$( () => {
	new RankMathUpdateSEOScoreTool()
} )
