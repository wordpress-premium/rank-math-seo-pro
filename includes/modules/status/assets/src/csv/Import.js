/* global confirm, ajaxurl */
/**
 * External Dependencies
 */
import jQuery from 'jquery'
import { isEmpty } from 'lodash'

/**
 * WordPress Dependencies
 */
import { __, sprintf } from '@wordpress/i18n'
import apiFetch from '@wordpress/api-fetch'
import { FormFileUpload } from '@wordpress/components'
import { useState, useEffect } from '@wordpress/element'

/**
 * Internal Dependencies
 */
import { Button, CheckboxControl, Notice } from '@rank-math/components'

const refreshProgress = ( setProgress ) => {
	jQuery.ajax( {
		url: ajaxurl,
		type: 'GET',
		dataType: 'html',
		data: { action: 'csv_import_progress', _ajax_nonce: rankMath.csvProgressNonce },
	} )
		.done( ( data ) => {
			setProgress(
				{
					status: 'processing',
					content: data,
				}
			)
			if ( jQuery( data ).find( '#csv-import-progress-value' ).length ) {
				setTimeout( () => ( refreshProgress( setProgress ) ), 3000 )
			} else {
				setProgress(
					{
						status: 'completed',
						content: data,
					}
				)
			}
		} )
}

/**
 * Import Rank Math settings.
 */
export default ( { data } ) => {
	const [ importFile, setImportFile ] = useState( false )
	const [ noOverwrite, setOverwrite ] = useState( true )
	const [ importProgress, setProgress ] = useState( data.importProgress )

	useEffect( () => {
		if ( ! isEmpty( importProgress ) ) {
			refreshProgress( setProgress )
		}
	}, [] )

	return (
		<div
			id="rank-math-import-form"
			className="rank-math-import-csv-form field-form"
		>
			{
				isEmpty( importProgress ) &&
				<>
					<div>
						<label htmlFor="csv-import-me">
							<strong>{ __( 'CSV File', 'rank-math-pro' ) }</strong>
						</label>
					</div>

					<div>
						<FormFileUpload
							__next40pxDefaultSize
							accept=".csv"
							onChange={ ( event ) => setImportFile( event.currentTarget.files[ 0 ] ) }
						>
							<span className="import-file-button">{ __( 'Choose File', 'rank-math-pro' ) }</span>
							{ importFile && <span>{ importFile.name }</span> }
						</FormFileUpload>
						<br />
						<span className="validation-message">
							{ __( 'Please select a file to import.', 'rank-math-pro' ) }
						</span>
					</div>

					<div>
						<CheckboxControl
							__nextHasNoMarginBottom
							label={ __( 'Do not overwrite existing data', 'rank-math-pro' ) }
							help={ __( 'Check this to import meta fields only if their current meta value is empty.', 'rank-math-pro' ) }
							checked={ noOverwrite }
							onChange={ setOverwrite }
						/>
					</div>

					{
						importFile &&
						<div>
							<Notice status="warning" className="notice-connect-disabled">
								<span
									dangerouslySetInnerHTML={ {
										__html: sprintf(
											// Translators: Bold text.
											__(
												'%s It is recommended to save a database backup before using this option because importing malformed CSV can result in loss of data.',
												'rank-math-pro'
											),
											`<strong>${ __( 'Warning:', 'rank-math-pro' ) }</strong>`,
										),
									} }
								/>
							</Notice>
						</div>
					}
				</>
			}
			{
				! isEmpty( importProgress ) &&
				<div id="csv-import-progress-details">
					<span
						dangerouslySetInnerHTML={ {
							__html: importProgress.content,
						} }
					/>
				</div>
			}
			<footer>
				{
					isEmpty( importProgress ) &&
					<Button
						variant="primary"
						disabled={ importFile === false }
						onClick={ () => {
							// eslint-disable-next-line no-alert
							if ( ! confirm( __( 'Are you sure you want to import meta data from this CSV file?', 'rank-math-pro' ) ) ) {
								return
							}

							setProgress(
								{
									status: 'processing',
									content: __( 'Import process has started, this may take a few moments to complete.', 'rank-math-pro' ),
								}
							)

							const formData = new FormData()
							formData.append( 'csv-import-me', importFile )
							formData.append( 'no_overwrite', noOverwrite )

							apiFetch( {
								method: 'POST',
								headers: {},
								path: '/rankmath/v1/importCSV',
								body: formData,
							} )
								.catch( ( error ) => {
									alert( error.message )
								} )
								.then( ( response ) => {
									setImportFile( false )
									if ( ! response.success ) {
										alert( response.message )
										return
									}

									setProgress(
										{
											status: 'processing',
											content: response.message,
										}
									)
									setTimeout( () => ( refreshProgress( setProgress ) ), 3000 )
								} )
						} }
					>
						{ __( 'Import', 'rank-math-pro' ) }
					</Button>
				}
				{
					! isEmpty( importProgress ) && importProgress.status !== 'completed' &&
					<>
						<Button
							isDestructive={ true }
							className="button-link-delete csv-import-cancel"
							onClick={ () => {
								// eslint-disable-next-line no-alert
								if ( ! confirm( __( 'Are you sure you want to stop the import process?', 'rank-math-pro' ) ) ) {
									return
								}

								apiFetch( {
									method: 'POST',
									headers: {},
									path: '/rankmath/v1/cancelCsvImport',
								} )
									.catch( ( error ) => {
										alert( error.message )
									} )
									.then( ( response ) => {
										if ( response.type === 'error' ) {
											setProgress(
												{
													status: 'error',
													content: response.message,
												}
											)
											setTimeout( () => ( refreshProgress( setProgress ) ), 3000 )
											return
										}

										setProgress( {} )
									} )
							} }
						>
							{ __( 'Cancel Import', 'rank-math-pro' ) }
						</Button>
						<span className="input-loading"></span>
					</>
				}
			</footer>
		</div>
	)
}
