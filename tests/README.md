# Tests

## API contract checks

Run contract checks against a running app instance:

```sh
BASE_URL=http://localhost/erebus-web/public php tests/api/health_contract.php
php tests/api/catalog_routing_contract.php
php tests/api/catalog_config_alias_contract.php
BASE_URL=http://localhost/erebus-web/public php tests/api/fallback_permission_queue_contract.php
BASE_URL=http://localhost/erebus-web/public php tests/api/permission_lov_contract.php
BASE_URL=http://localhost/erebus-web/public php tests/api/operator_status_model_contract.php
BASE_URL=http://localhost/erebus-web/public php tests/api/schema_inventory_contract.php
BASE_URL=http://localhost/erebus-web/public php tests/api/permission_intelligence_split_contract.php
php tests/api/permission_queue_vocabulary_contract.php
BASE_URL=http://localhost/erebus-web/public php tests/api/classification_gaps_contract.php
BASE_URL=http://localhost/erebus-web/public php tests/api/vt_confidence_contract.php
BASE_URL=http://localhost/erebus-web/public php tests/api/analysis_fusion_contract.php
BASE_URL=http://localhost/erebus-web/public php tests/api/sample_detail_platform_contract.php
```

`BASE_URL` should point to the host serving `/api.php/*.php`.
For this checkout under `/var/www/html/erebus-web`, that means including
`/erebus-web/public` unless your web server maps a dedicated vhost directly to
the repo's `public/` directory.

The contract checks intentionally validate safe unavailable responses as well as
fully available schemas, so they can run before every database surface exists.
