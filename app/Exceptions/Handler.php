use Illuminate\Auth\AuthenticationException;

protected function unauthenticated($request, AuthenticationException $exception)
{
return response()->json([
'message' => 'Unauthenticated'
], 401);
}