# Elastic Compare
Tool to find differences between documents in two elasticsearch indices.

## How to use
```bash
composer install

php ./src/index.php source_index source_type target_index target_type [host]
```

```bash
php ./src/index.php catalog_products_multi_index_nl_1 product catalog_products_nl_1 product http://127.0.0.1:9500
```
