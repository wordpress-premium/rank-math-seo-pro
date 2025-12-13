/**
 * External Dependencies
 */
import { map, pull, includes, isEmpty } from 'lodash'

/**
 * WordPress Dependencies
 */
import { __ } from '@wordpress/i18n'
import { useState } from '@wordpress/element'

/**
 * Internal Dependencies
 */
import { Button, TextControl } from '@rank-math/components'
import CheckBoxControl from './CheckBoxControl'

export default ( { data } ) => {
	const hashDescriptions = {
		post: __( 'Post types:', 'rank-math-pro' ),
		term: __( 'Taxonomies:', 'rank-math-pro' ),
		user: __( 'User Roles:', 'rank-math-pro' ),
	}
	const hashLabels = {
		post: __( 'Posts', 'rank-math-pro' ),
		term: __( 'Terms', 'rank-math-pro' ),
		user: __( 'Users', 'rank-math-pro' ),
	}
	const objectTypes = {
		post: 'post_types',
		term: 'taxonomies',
		user: 'roles',
	}
	const [ advanceOptions, useAdvanceOptions ] = useState( false )
	const [ exportData, setExportData ] = useState( {
		post: Object.keys( data.exportData.post ),
		term: Object.keys( data.exportData.term ),
		user: Object.keys( data.exportData.user ),
	} )

	return (
		<form id="csv-export-form" className="rank-math-export-form field-form" action="" method="post">
			<ul>
				{
					map( data.exportData, ( objectData, object ) => {
						return (
							<li key={ object }>
								<CheckBoxControl
									id={ 'object_type_' + object }
									label={ hashLabels[ object ] }
									name="object_types[]"
									value={ object }
									checked={ ! isEmpty( exportData[ object ] ) }
									onChange={ ( e ) => {
										exportData[ object ] = e.target.checked ? Object.keys( objectData ) : []
										setExportData( { ...exportData } )
									} }
								/>

								{
									advanceOptions &&
									<div className="csv-advanced-options">
										<p className="description">{ hashDescriptions[ object ] }</p>
										<ul className="rank-math-checkbox-list">
											{
												map( objectData, ( name, type ) => {
													return (
														<li key={ name }>
															<CheckBoxControl
																id={ type }
																label={ name }
																name={ objectTypes[ object ] + '[]' }
																value={ type }
																checked={ includes( exportData[ object ], type ) }
																onChange={ ( e ) => {
																	if ( e.target.checked ) {
																		exportData[ object ].push( type )
																	} else {
																		pull( exportData[ object ], type )
																	}
																	setExportData( { ...exportData } )
																} }
															/>
														</li>
													)
												} )
											}
										</ul>
									</div>
								}
							</li>
						)
					} )
				}
			</ul>

			<div>
				<p className="description">{ __( 'Choose the object types to export.', 'rank-math-pro' ) }</p>
				<CheckBoxControl
					id="use-advanced-options"
					label={ __( 'Use advanced options', 'rank-math-pro' ) }
					name="use_advanced_options"
					value={ true }
					checked={ advanceOptions }
					onChange={ ( event ) => useAdvanceOptions( event.target.checked ) }
				/>
			</div>

			<footer>
				<TextControl
					type="hidden"
					className="hidden"
					name="rank_math_pro_csv_export"
					value="1"
				/>

				<TextControl
					type="hidden"
					name="_wpnonce"
					value={ data.exportCsvNonce }
				/>

				<Button
					variant="primary"
					disabled={ isEmpty( exportData ) }
					type="submit"
				>
					{ __( 'Export', 'rank-math' ) }
				</Button>
			</footer>
		</form>
	)
}
