/**
 * WordPress Dependencies
 */
import { __ } from '@wordpress/i18n'
import { TabPanel } from '@wordpress/components'

/**
 * Internal Dependencies
 */
import Import from './csv/Import'
import Export from './csv/Export'
import './CSVFile.scss'

const tabs = [
	{
		name: 'rank-math-import-csv',
		title: (
			<>
				<i className="rm-icon rm-icon-import" />
				<span className="rank-math-tab-text">
					{ __( 'Import CSV', 'rank-math' ) }
				</span>
			</>
		),
		view: Import,
	},
	{
		name: 'rank-math-export-csv',
		title: (
			<>
				<i className="rm-icon rm-icon-export" />
				<span className="rank-math-tab-text">
					{ __( 'Export CSV', 'rank-math' ) }
				</span>
			</>
		),
		view: Export,
	},
]

export default ( props ) => {
	return (
		<div className="import-export-csv">
			<h2>{ __( 'CSV File', 'rank-math-pro' ) }</h2>
			<p className="description">{ __( 'Import SEO meta data for posts, terms, and users from a CSV file.', 'rank-math-pro' ) }</p>

			<div className="rank-math-box no-padding">
				<TabPanel tabs={ tabs }>
					{ ( { view: View } ) => (
						<div className="rank-math-box-content">
							<View data={ props.data } />
						</div>
					) }
				</TabPanel>
			</div>
		</div>
	)
}
