/**
 * WordPress Dependencies
 */
import { __ } from '@wordpress/i18n'
import { Dashicon } from '@wordpress/components'

/**
 * Internal Dependencies
 */
import { Button } from '@rank-math/components'

export default () => {
	return (
		<>
			<div className="print-logo">
				<img src={ rankMath.printLogo } alt="Rank Math Logo" />
			</div>
			<Button
				className="rank-math-print-results"
				onClick={ () => ( window.print() ) }
			>
				<Dashicon icon="printer" />
				{ __( 'Print', 'rank-math-pro' ) }
			</Button>
		</>
	)
}
