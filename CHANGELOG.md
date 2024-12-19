# Changelog

## [1.17.0](https://github.com/webmappsrl/osm2cai2/compare/v1.16.0...v1.17.0) (2024-12-19)


### Features

* added almost all elements to login with the cas system ([7509ba6](https://github.com/webmappsrl/osm2cai2/commit/7509ba666e9218eb290753806fcbf4b2d35704a6))
* configured log viewer ([a1d089c](https://github.com/webmappsrl/osm2cai2/commit/a1d089cc64071572d261ef59f66b10f36e7da57d))
* dashboards OC: 4224 ([#79](https://github.com/webmappsrl/osm2cai2/issues/79)) ([ce5cfc6](https://github.com/webmappsrl/osm2cai2/commit/ce5cfc6b8c1ee5691af47d1ad82921a4cd0a15b2))
* e2e tests setup OC:4057 ([#65](https://github.com/webmappsrl/osm2cai2/issues/65)) ([f3e8b61](https://github.com/webmappsrl/osm2cai2/commit/f3e8b615f0ebfc4705757b7c297cc9b74c14c7b7))
* Main Update ([#89](https://github.com/webmappsrl/osm2cai2/issues/89)) ([b8c36e0](https://github.com/webmappsrl/osm2cai2/commit/b8c36e0877b5a179331ac474061856b47189cb35))
* Permessi Utente OC:4228 ([#78](https://github.com/webmappsrl/osm2cai2/issues/78)) ([3cc9988](https://github.com/webmappsrl/osm2cai2/commit/3cc998865c3e8e815abe81e32e5ec3fd8ab2357f))
* sync ugc from legacy osm2cai OC: 3470 ([#71](https://github.com/webmappsrl/osm2cai2/issues/71)) ([8dc5b63](https://github.com/webmappsrl/osm2cai2/commit/8dc5b633afccacb5a63cdae95c93e543eff5292f))
* update nova Login.vue oc: 4227 ([99ad919](https://github.com/webmappsrl/osm2cai2/commit/99ad91937dca7bca625be94824ab2e6e5a4c3823))
* update the wm-package oc: 4227 ([e3e494d](https://github.com/webmappsrl/osm2cai2/commit/e3e494d7930656742cef3ccc8dca3aa6d905d12b))


### Bug Fixes

* cas auth oc: 4227 ([08d545b](https://github.com/webmappsrl/osm2cai2/commit/08d545bc310923d6fc388cb955d0b588a798fd39))
* deploy prod workflow ([b0512d9](https://github.com/webmappsrl/osm2cai2/commit/b0512d9947b41820d7b35001da203009f236ae8d))
* dev deploy scripts to inherit secrets ([b08bd77](https://github.com/webmappsrl/osm2cai2/commit/b08bd77ebc0ba8be405d7090f790c0df60c69ab9))
* geojson controller test ([ae6973f](https://github.com/webmappsrl/osm2cai2/commit/ae6973f903bafff0a2672a5155b6f3c07a3d3440))
* geojson controller test ([84a4383](https://github.com/webmappsrl/osm2cai2/commit/84a43833142500d121fcdee68b04e7b1967f7406))
* gitmodules file ([e5875a0](https://github.com/webmappsrl/osm2cai2/commit/e5875a04c3ea67786d45b9c90cd01b34047daba6))
* submodules dependencies in tests workflow ([3ef7036](https://github.com/webmappsrl/osm2cai2/commit/3ef7036928f9ff2311644f46fc738cfb52168b17))


### Miscellaneous Chores

* Add submodules for wm-osmfeatures and wm-package ([8a517db](https://github.com/webmappsrl/osm2cai2/commit/8a517db8b687990fe151b6b183bd4bc04c394e41))
* fixes and performance improvements ([#91](https://github.com/webmappsrl/osm2cai2/issues/91)) ([c251f2a](https://github.com/webmappsrl/osm2cai2/commit/c251f2ab98c040f9b6e392d9cadb0fd62fcb5b08))
* installed log viewer package ([2b23ffc](https://github.com/webmappsrl/osm2cai2/commit/2b23ffc76f8f4cbafa326bbf96a6a054bdc5fab3))
* recalculate intersections job now extends basejob class from wm-package ([425493c](https://github.com/webmappsrl/osm2cai2/commit/425493cdf6257bab95c62fd9f255c0ce29514ba0))
* updated docker config for horizon and redis ([35bdbe6](https://github.com/webmappsrl/osm2cai2/commit/35bdbe6ec0d06018036f6961141896356e15ebd0))
* updated packages as submodules ([81cc03e](https://github.com/webmappsrl/osm2cai2/commit/81cc03e701ddb9ef7d4c037370c77613388657b6))

## [1.16.0](https://github.com/webmappsrl/osm2cai2/compare/v1.15.0...v1.16.0) (2024-05-09)


### Features

* added filter for user to ecpoi ([648ea32](https://github.com/webmappsrl/osm2cai2/commit/648ea32a2a37a90bb5d42fe2888306e3e4fbdcb3))
* added osmfeatures data to detail province nova resource ([9727f94](https://github.com/webmappsrl/osm2cai2/commit/9727f94c8ea89876bee481d3db6945ecdbfd1978))
* added osmfeatures_data and clickable osmfeatures id to municipality nova resource ([13e827d](https://github.com/webmappsrl/osm2cai2/commit/13e827de869b09ec14a767f935d021cb2c40d442))
* added osmfeatures_data and clickable osmfeatures id to region nova resource ([e03cecf](https://github.com/webmappsrl/osm2cai2/commit/e03cecfc50b24df5bf08c7d61a8d622babf3c83e))
* added osmfeatures_data to ec poi detail ([c035a1e](https://github.com/webmappsrl/osm2cai2/commit/c035a1e95bc7f2a4485a8c68d91d3e7d7147ed68))
* added source filter and website filter for ec pois ([b315424](https://github.com/webmappsrl/osm2cai2/commit/b315424b4500758640a0e845a15e470bfede59b9))
* main dashboard first version ([a957323](https://github.com/webmappsrl/osm2cai2/commit/a9573233db00754c319c12f3c1367f231d21fc0e))
* osm2cai helper class enhancement ([21359e1](https://github.com/webmappsrl/osm2cai2/commit/21359e1a20c51b9235d0b90284cb331e38e519d9))
* province nova resource searchable by osmfeatures id ([18e5fa1](https://github.com/webmappsrl/osm2cai2/commit/18e5fa1217544c198387704f6af4ae6b23c20c09))
* updated dependencies ([a68240e](https://github.com/webmappsrl/osm2cai2/commit/a68240e495e4ce4410f070d37e80e0336dafc31e))
* updated municipality model casts ([c8d703e](https://github.com/webmappsrl/osm2cai2/commit/c8d703e9f5405935a10d3c550073cb61e19bdba6))

## [1.15.0](https://github.com/webmappsrl/osm2cai2/compare/v1.14.0...v1.15.0) (2024-05-07)


### Features

* added wiki filters ([e7aa6d2](https://github.com/webmappsrl/osm2cai2/commit/e7aa6d2989da4f7f0bd80fd7229aa7264e45f209))

## [1.14.0](https://github.com/webmappsrl/osm2cai2/compare/v1.13.0...v1.14.0) (2024-05-07)


### Features

* added score nova filter for ec pois ([38cf453](https://github.com/webmappsrl/osm2cai2/commit/38cf453dfde94ec78ea74bee82caa7755ab77d5d))
* added user id and elevation to ec_poi ([63100b4](https://github.com/webmappsrl/osm2cai2/commit/63100b4cda94e4b4c2849c5c550a7ba189f5200e))
* added user relation to nova resource ec poi ([26a589b](https://github.com/webmappsrl/osm2cai2/commit/26a589b52f4676f7b7fc5d14a071f199d01964ba))
* created associate user ec poi command ([7e1e564](https://github.com/webmappsrl/osm2cai2/commit/7e1e564e7422f5b81d7a58b638c3871c4cc68e4b))
* defined relation between ecpoi and user ([6089808](https://github.com/webmappsrl/osm2cai2/commit/6089808630a22a28d2702dc3147d7ba47bf40819))

## [1.13.0](https://github.com/webmappsrl/osm2cai2/compare/v1.12.0...v1.13.0) (2024-05-07)


### Features

* ecpoi nova resource update ([80e4f73](https://github.com/webmappsrl/osm2cai2/commit/80e4f73a1b2bcc334bce3f175be8a53c7f9f3d4e))

## [1.12.0](https://github.com/webmappsrl/osm2cai2/compare/v1.11.0...v1.12.0) (2024-05-07)


### Features

* added osm2cai config file ([8fbfc8e](https://github.com/webmappsrl/osm2cai2/commit/8fbfc8eada76d40aff0ecf8bb1c97437f022cd18))
* added tags mapping trait ([8e6b95f](https://github.com/webmappsrl/osm2cai2/commit/8e6b95f15a502a5ea23caad2b5b03fc61f23b5e4))
* added type mapping for ec poi ([9850118](https://github.com/webmappsrl/osm2cai2/commit/9850118a253cc03421b0128e1045e322d31a19ab))
* created ec pois model and nova resource ([bd2f46b](https://github.com/webmappsrl/osm2cai2/commit/bd2f46bcc187d04ca9d2c60f624afd51311c0655))
* ec pois migration ([287059b](https://github.com/webmappsrl/osm2cai2/commit/287059bd420a6615a1bd41171176d59dbc548eef))
* initialized ecPoi model for osmfeatures import ([49e1ff7](https://github.com/webmappsrl/osm2cai2/commit/49e1ff7a21496a750682817f5c4e35b19fa811c7))
* updated wm-osmfeatures package dependency ([09cf0d1](https://github.com/webmappsrl/osm2cai2/commit/09cf0d168b015fe61b884bb7fabdc593a21846f6))


### Bug Fixes

* typo in ec poi class ([2798e90](https://github.com/webmappsrl/osm2cai2/commit/2798e9014569f4ca9a32c9230a17d0df39022ec2))

## [1.11.0](https://github.com/webmappsrl/osm2cai2/compare/v1.10.0...v1.11.0) (2024-05-06)


### Features

* updated wm-osmfeatures package version ([ac454d1](https://github.com/webmappsrl/osm2cai2/commit/ac454d159fe104665596f5b1e6634bc65a1ec06f))

## [1.10.0](https://github.com/webmappsrl/osm2cai2/compare/v1.9.0...v1.10.0) (2024-05-06)


### Features

* required wm-osmfeatures package by composer ([f04f912](https://github.com/webmappsrl/osm2cai2/commit/f04f912d5c9fca5ec099ee02f693c057c5074f51))

## [1.9.0](https://github.com/webmappsrl/osm2cai2/compare/v1.8.0...v1.9.0) (2024-05-04)


### Features

* created province model and defined policies ([b6d51d8](https://github.com/webmappsrl/osm2cai2/commit/b6d51d879d9e1157f8fd77522dc5b595c562b035))
* created region model ([ad579eb](https://github.com/webmappsrl/osm2cai2/commit/ad579ebde7d06dcddb27fd9e92fce2111b6b5db2))
* created region nova resource and defined index and details fields ([56c78a7](https://github.com/webmappsrl/osm2cai2/commit/56c78a7b390a759152348ccda93ed7abf4192128))
* implemented province nova resource ([86a2001](https://github.com/webmappsrl/osm2cai2/commit/86a2001ca8e416f958e69371f5ff4a1fac4420fd))
* initialized province model for osmfeatures sync ([69147ae](https://github.com/webmappsrl/osm2cai2/commit/69147aec3749c7d1e7d53abdb89d78a4192ef45f))
* initialized region model for osmfeatures sync ([aaee38e](https://github.com/webmappsrl/osm2cai2/commit/aaee38e70efc954a7abbd411bd7d58108b227c82))


### Bug Fixes

* changed queue timeout time ([0dcc90c](https://github.com/webmappsrl/osm2cai2/commit/0dcc90c76e5efca55bfea48ad012f4d10b95b837))

## [1.8.0](https://github.com/webmappsrl/osm2cai2/compare/v1.7.0...v1.8.0) (2024-05-03)


### Features

* implemented wm-osmfeatures configuration ([fd3decf](https://github.com/webmappsrl/osm2cai2/commit/fd3decfd0bd06f7d9c62d0b97888a7f54a0f063a))

## [1.7.0](https://github.com/webmappsrl/osm2cai2/compare/v1.6.0...v1.7.0) (2024-05-01)


### Features

* created municipalities model, nova resource an configured policies ([d64412c](https://github.com/webmappsrl/osm2cai2/commit/d64412cbbd96179c82b2ffb5dd565f8764ba859d))

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
