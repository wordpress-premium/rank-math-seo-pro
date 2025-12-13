/**
 * WordPress Dependencies
 */
import { useContext } from '@wordpress/element'
import AnalyzerContextProvider, { AnalyzerContext } from './AnalyzerContextProvider'

const useAnalyzerContext = () => {
	const context = useContext( AnalyzerContext )

	return context
}

export { useAnalyzerContext, AnalyzerContextProvider }
