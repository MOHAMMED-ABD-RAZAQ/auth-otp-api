<?php

namespace Ma\AuthOtpApi\Traits;

trait ResponseTrait
{

    private $isOnlyBody = false;

    public function onlyBody()
    {
        $this->isOnlyBody = true;
    }

    public function responseError($payload = null, string $message = "", array $attrs = [], int $statusCode = 422, array $headers = [])
    {
        if ($this->isOnlyBody) {
            return [
                'error' => true,
                'message' => [
                    'en'    =>  trans($message, $attrs, 'en'),
                    'ar'    =>  trans($message, $attrs, 'ar'),
                ],
                'payload' => $payload
            ];
        } else {
            return response()->json([
                'error' => true,
                'message' => [
                    'en'    =>  trans($message, $attrs, 'en'),
                    'ar'    =>  trans($message, $attrs, 'ar'),
                ],
                'payload' => $payload
            ], $statusCode, $headers);
        }
    }

    /**
     * Response json with success status
     *
     * @param array|null $payload
     * @param string $message
     * @param int $statusCode
     * @param array $headers
     * @return JsonResponse| array
     */
    public function responseSuccess($payload = null, string $message = "", array $attrs = [], int $statusCode = 200, array $headers = [])
    {
        if ($this->isOnlyBody) {
            return [
                'error' => false,
                'message' => [
                    'en'    =>  trans($message, $attrs, 'en'),
                    'ar'    =>  trans($message, $attrs, 'ar'),
                ],
                'payload' => $payload
            ];
        } else {
            return response()->json([
                'error' => false,
                'message' => [
                    'en'    =>  trans($message, [], 'en'),
                    'ar'    =>  trans($message, [], 'ar'),
                ],
                'payload' => $payload
            ], $statusCode, $headers);
        }
    }
}
