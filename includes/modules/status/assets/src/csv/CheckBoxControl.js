export default ( props ) => {
	return (
		<>
			<input type="checkbox" { ...props } />
			<label htmlFor={ props.id }>{ props.label }</label>
		</>
	)
}
