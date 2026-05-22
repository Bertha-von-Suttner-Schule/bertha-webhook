<?php

declare(strict_types=1);

return [
	'routes' => [
		['name' => 'settings#index', 'url' => '/api/v1/admin/settings', 'verb' => 'GET'],
		['name' => 'settings#update', 'url' => '/api/v1/admin/settings', 'verb' => 'PUT'],
	],
];
