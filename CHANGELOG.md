# Changelog

## [1.6.0](https://github.com/webmappsrl/osm2cai2/compare/v1.5.0...v1.6.0) (2024-04-29)


### Features

* added db:download script ([74d3c17](https://github.com/webmappsrl/osm2cai2/commit/74d3c17761ea342f763fcb9533ae28b3ebcf78cf))
* added wm map multipolygon field ([eb20de6](https://github.com/webmappsrl/osm2cai2/commit/eb20de6bf2df17715517556b75779e29c46d25d8))

## [1.5.0](https://github.com/webmappsrl/osm2cai2/compare/v1.4.0...v1.5.0) (2024-04-26)


### Features

* added area model ([0e82dc1](https://github.com/webmappsrl/osm2cai2/commit/0e82dc13edb6e5604d18be5b8691bf02a1d7c18d))
* areas nova resource ([d07cf4c](https://github.com/webmappsrl/osm2cai2/commit/d07cf4c6ffdcf709f35e2de24931cb67a12589f6))
* updated import job to areas ([956d4fc](https://github.com/webmappsrl/osm2cai2/commit/956d4fcb326472e8890b29eac32033bf80bf847a))

## [1.4.0](https://github.com/webmappsrl/osm2cai2/compare/v1.3.2...v1.4.0) (2024-04-26)


### Features

* created sector model ([dbe6680](https://github.com/webmappsrl/osm2cai2/commit/dbe6680c911161967e75c90fec06f26c34060026))
* sector nova resource ([5bc5d0d](https://github.com/webmappsrl/osm2cai2/commit/5bc5d0df75f399ea42f5431d9c84b04bbd1b8ef4))

## [1.3.2](https://github.com/webmappsrl/osm2cai2/compare/v1.3.1...v1.3.2) (2024-04-25)


### Miscellaneous Chores

* added error handling to import job ([966bb64](https://github.com/webmappsrl/osm2cai2/commit/966bb64ee62c46cee17ed50742fc6763616daeca))

## [1.3.1](https://github.com/webmappsrl/osm2cai2/compare/v1.3.0...v1.3.1) (2024-04-25)


### Miscellaneous Chores

* added better error handling in import job ([b680ada](https://github.com/webmappsrl/osm2cai2/commit/b680adada88740bdbd729dc6f0162a54b607aa7d))

## [1.3.0](https://github.com/webmappsrl/osm2cai2/compare/v1.2.1...v1.3.0) (2024-04-25)


### Features

* created club model ([6097948](https://github.com/webmappsrl/osm2cai2/commit/60979480d2f3077ced09dd62697cd8d228cda7ca))
* registered club nova resource ([17d75f3](https://github.com/webmappsrl/osm2cai2/commit/17d75f3f28681daaa28d3c934d72154e93e952d9))


### Bug Fixes

* fixed migration to accept null value  geometries ([641b535](https://github.com/webmappsrl/osm2cai2/commit/641b535c15aab68485824c4e7d0493299451e9cd))
* fixed null geometry handling in import job ([a34f7d1](https://github.com/webmappsrl/osm2cai2/commit/a34f7d13fc4fc92dc2949b736fd045fd02b4070d))

## [1.2.1](https://github.com/webmappsrl/osm2cai2/compare/v1.2.0...v1.2.1) (2024-04-24)


### Bug Fixes

* fixed import command ([88f396f](https://github.com/webmappsrl/osm2cai2/commit/88f396f52328cfdfa3b186114e98b7c49509cd68))

## [1.2.0](https://github.com/webmappsrl/osm2cai2/compare/v1.1.0...v1.2.0) (2024-04-24)


### Features

* added cai hut model ([ba5cfed](https://github.com/webmappsrl/osm2cai2/commit/ba5cfedd3b5be9aea094ecebf26f8e7d156e21b1))
* added cai hut nova resource with index and detail configured ([2255250](https://github.com/webmappsrl/osm2cai2/commit/2255250cd90d351af7b87792c827da554555c879))
* updated sync command to import cai huts ([98780a1](https://github.com/webmappsrl/osm2cai2/commit/98780a1885608ce42481b2f5d582cba2e083383f))


### Bug Fixes

* added name to search parameters for mountain groups and natural springs ([5efe69e](https://github.com/webmappsrl/osm2cai2/commit/5efe69e0047fd296eeed858b14e7efef38cd5a8b))

## [1.1.0](https://github.com/webmappsrl/osm2cai2/compare/v1.0.0...v1.1.0) (2024-04-24)


### Features

* added footer to nova ([c137b89](https://github.com/webmappsrl/osm2cai2/commit/c137b89b6349038eb7c9281582fd8c9d934f71b6))
* created job table in db ([5b6c469](https://github.com/webmappsrl/osm2cai2/commit/5b6c469ffe5116f9f7e5a32782bb69108e6bd6c0))
* created mountain group model and policies ([12c36e8](https://github.com/webmappsrl/osm2cai2/commit/12c36e871f6dfb5fbfdd35ff552ed1b6eae292d0))
* created mountain_groups table ([522aed1](https://github.com/webmappsrl/osm2cai2/commit/522aed10f1e86059e7b98cfc1a58a9ad3b3cfb83))
* implemented laravel queue monitor package ([cfa33c9](https://github.com/webmappsrl/osm2cai2/commit/cfa33c9fe6337a82f58dce1c5ce7aede69189c26))
* import job first version ([cef9cd5](https://github.com/webmappsrl/osm2cai2/commit/cef9cd5b683bfd5e54c6c563ab3592328f9bd236))
* osm2cai sync command ([322ea90](https://github.com/webmappsrl/osm2cai2/commit/322ea90ed5b1c6356106a297637af5b81d1e48f8))
* updated mountain groups nova detail ([afa4d69](https://github.com/webmappsrl/osm2cai2/commit/afa4d695d5ddc20d15c9e76cd56ee3cb260c5b74))


### Bug Fixes

* fixed xdebug conf ([7d75c22](https://github.com/webmappsrl/osm2cai2/commit/7d75c224c5c926d195465559bdaff4b38eb22074))

## 1.0.0 (2024-04-22)


### Bug Fixes

* fixed deploy prod script ([92a5ce4](https://github.com/webmappsrl/osm2cai2/commit/92a5ce4f0efeabc07927b1d13a1096f638aaa2f5))
* fixed deploy prod script ([c4d3e66](https://github.com/webmappsrl/osm2cai2/commit/c4d3e669291344937101824d57415b93651afcfc))
