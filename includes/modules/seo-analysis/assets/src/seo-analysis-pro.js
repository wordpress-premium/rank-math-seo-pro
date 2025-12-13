/**
 * External Dependencies
 */
import { map } from 'lodash'

/**
 * WordPress Dependencies
 */
import { addFilter } from '@wordpress/hooks'
import domReady from '@wordpress/dom-ready'
import { __ } from '@wordpress/i18n'

/**
 * Internal Dependencies
 */
import PrintButton from './components/PrintButton'
import { getStore } from './store'
import CompetitorAnalysis from './components/CompetitorAnalysis'
import SideBySide from './components/SideBySide'

getStore()

domReady( () => {
	if ( ! rankMath.isWpRocketActive ) {
		addFilter(
			'rank_math_analysis_category_notice',
			'rank-math-pro',
			( notice, category ) => {
				if ( 'performance' !== category ) {
					return notice
				}

				return (
					<div className="rank-math-seo-analysis-notice rank-math-seo-analysis-notice-wp-rocket">
						<a
							href="https://wp-rocket.me/rankmath-and-wp-rocket/?utm_campaign=rankmath-benefits&utm_source=rankmath&utm_medium=partners"
							target="_blank"
							rel="noopener noreferrer"
						>
							{ __(
								'Install WP Rocket to get the Performance Boost',
								'rank-math-pro'
							) }
						</a>
					</div>
				)
			}
		)
	}

	// Overwrite Competitor Analysis view & add Side By Side Tab.
	addFilter(
		'rank_math_analyzer_tabs',
		'rank-math-pro',
		( tabs ) => {
			tabs = map( tabs, ( field ) => {
				if ( field.name === 'competitor_analyzer' ) {
					return {
						...field,
						view: CompetitorAnalysis,
					}
				}
				return field
			} )

			const sideBySideTab = {
				name: 'side_by_side',
				title: (
					<>
						<i className="dashicons dashicons-columns" />
						{ __( 'Side By Side', 'rank-math' ) }
					</>
				),
				view: SideBySide,
			}

			tabs.splice( 1, 0, sideBySideTab ) // Add Side By Side at 2nd position

			return tabs
		}
	)

	// Add Print button with Logo.
	addFilter(
		'rank_math_seo_analysis_print_result',
		'rank-math-pro',
		() => (
			<PrintButton />
		)
	)

	/**
	 * Move Admin License notice below Breadcrumbs
	 */
	const moveNoticeBelowBreadcrumbs = () => {
		const notice = document.querySelector( '.admin-license-notice' )
		const targetLocation = document.querySelector( '.rank-math-breadcrumbs-wrap' )

		if ( notice && targetLocation ) {
			targetLocation.insertAdjacentElement( 'afterend', notice )
		}
	}

	setTimeout( () => {
		moveNoticeBelowBreadcrumbs()
	}, 1 )
} )
