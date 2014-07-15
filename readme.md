katapi

API that exposes friendly representations (currently JSON and partly HTML)
of MARC21-encoded resources. MARC21 data is retrieved from a OAI-PMH repository
and stored in a local MongoDB store, or retrieved directly from SRU service
(currently hardcoded to use the BIBSYS SRU service).

Harvesting OAI-PMH data:

```bash
php artisan harvest:bibsys --url http://oai.bibsys.no/repository \
  --set urealSamling42 --from=2014-01-01 --until=2014-02-01
```

Work in progress. Running at [katapi.biblionaut.net](//katapi.biblionaut.net/)
