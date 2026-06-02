if($args.Count -eq 0) {
	$drive = "E:"
} else {
	$drive = $args[0]
}

$obj = New-Object -comObject Shell.Application
$obj.Namespace(17).ParseName("$drive").InvokeVerb('Eject')
