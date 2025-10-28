# Changelog

## 1.0.0 (2025-10-28)


### Features

* add search normalization for osm_id oc: 6428 ([#231](https://github.com/webmappsrl/osm2cai2/issues/231)) ([c9ac628](https://github.com/webmappsrl/osm2cai2/commit/c9ac62867ce97f7e115dbcdaef67298fc71d82f8))
* **backup:** ✨ add database backup script with symbolic link creation ([e87ded8](https://github.com/webmappsrl/osm2cai2/commit/e87ded83f3ccc71cf68c9e0df94b6aba274eac05))
* **database:** ✨ add properties column to users table ([26a6701](https://github.com/webmappsrl/osm2cai2/commit/26a670186a82f080ae6750ff3a9e74a85e70c468))
* **deployment:** ✨ add SSL and non-SSL Apache configuration for osm2cai.prod.maphub.it ([ff8d92e](https://github.com/webmappsrl/osm2cai2/commit/ff8d92ed86b95780f431b4057092427397b0df7c))
* **models:** ✨ add method to clean pivot table relationships in EcPoi OC:6387 ([#234](https://github.com/webmappsrl/osm2cai2/issues/234)) ([2fcd3a6](https://github.com/webmappsrl/osm2cai2/commit/2fcd3a6882672b6951f516c2cdb32f1d3af260d1))
* **models:** ✨ add new fillable attributes to HikingRoute class ([#235](https://github.com/webmappsrl/osm2cai2/issues/235)) ([36885c2](https://github.com/webmappsrl/osm2cai2/commit/36885c2c6aa2ed1992b0e36a3394d25b6260b433))
* **nova:** ✨ add GeoHub indicator to 'Created by' field OC 6496 ([#236](https://github.com/webmappsrl/osm2cai2/issues/236)) ([9e2db06](https://github.com/webmappsrl/osm2cai2/commit/9e2db06696e578bc317940c46d040a1ab3343691))
* **observer:** ✨ add afterCommit property and saved event handler ([3f655c7](https://github.com/webmappsrl/osm2cai2/commit/3f655c7ef386f568830c97efe04cb9ae577fedd0))
* **scripts:** ✨ add initialization and update for git submodules and composer dependencies ([fbdcd83](https://github.com/webmappsrl/osm2cai2/commit/fbdcd8314f0abc483bc2ee81824cd56d92312073))
* **scripts:** ✨ enhance database backup and Elasticsearch setup ([68dfb84](https://github.com/webmappsrl/osm2cai2/commit/68dfb84dbc24f3f887dd53fc5de11a8c68e74c06))
* **scripts:** ✨ load environment variables for container names ([a9f58d6](https://github.com/webmappsrl/osm2cai2/commit/a9f58d6ead6e17cc13f70b8a91e2b53f899a29f4))
* **webhook:** ✨ unify UGC POI and Track webhook handling ([7f5f104](https://github.com/webmappsrl/osm2cai2/commit/7f5f104af6767768b2fb23f88e54521876d60a9d))
* **wm-package-integration:** ✨ add Apache configuration for osm2cai.cai.it ([f8dbd15](https://github.com/webmappsrl/osm2cai2/commit/f8dbd15c0aba5b8a928cc3106d5b05863a6bab7b))


### Bug Fixes

* **logging:** 🔧 change logger configuration for wm-osmfeatures ([566f497](https://github.com/webmappsrl/osm2cai2/commit/566f4970ee1c9cd396b92e97bd101d1b4af58305))
* **models:** 🐛 add null safe operator for layers in HikingRoute ([e9c9539](https://github.com/webmappsrl/osm2cai2/commit/e9c953913a6bc1fed843c8de0e208adfe6b21ff1))
* **scripts:** 🐛 correct symbolic link path in backup-db.sh ([93acde5](https://github.com/webmappsrl/osm2cai2/commit/93acde57a8ab2b9462bc85f430c865e86a202275))


### Miscellaneous Chores

* **gitignore:** ➕ add public/wm-osmfeatures.log to .gitignore ([8bc2f34](https://github.com/webmappsrl/osm2cai2/commit/8bc2f34c8c14ec31703770912ec52e1117416f94))
* **schedule:** 🔧 comment out obsolete command ([3c83a98](https://github.com/webmappsrl/osm2cai2/commit/3c83a981b7245ce565f1d43f4a8bba588be1bb98))
* **script:** 🔧 remove Elasticsearch permissions fix step ([30bb9c5](https://github.com/webmappsrl/osm2cai2/commit/30bb9c5cdf93e1f53195a1924f7bc6bdec63e436))

## [1.1.1](https://github.com/webmappsrl/osm2cai2/compare/v1.1.0...v1.1.1) (2025-04-24)


### Bug Fixes

* imported missing class ([d08de37](https://github.com/webmappsrl/osm2cai2/commit/d08de3776233b3684cf876f2df0d9146ef661961))
* nova global search for hiking routes, provinces and cai huts ([b2ccf74](https://github.com/webmappsrl/osm2cai2/commit/b2ccf7484fc4f2130f67e3e78534777da0b92676))
* nova global search for hiking routes, provinces and cai huts ([8a907ec](https://github.com/webmappsrl/osm2cai2/commit/8a907ecf66b18a6311f67f5d43a60cea02692a18))
* osmfeatures_data in update hiking route command OC:5336 ([6012f84](https://github.com/webmappsrl/osm2cai2/commit/6012f843b3e4cf19a13b969f033d339116214e15))
* osmfeatures_data in update hiking route command OC:5336 ([6783375](https://github.com/webmappsrl/osm2cai2/commit/678337542b6921e240873732163d4b28c9f4a26e))
* source surveys monitorings and api documentation OC:5386,5387 ([96a59cc](https://github.com/webmappsrl/osm2cai2/commit/96a59cc36586d5c5b00677133b8ea11a1a2fe2f9))


### Miscellaneous Chores

* update wm-package subproject commit reference to 4887733 ([2939705](https://github.com/webmappsrl/osm2cai2/commit/2939705fad151109069d88dbe6de49e9f39fe002))
* update wm-package subproject commit reference to b8c1830 ([9b50349](https://github.com/webmappsrl/osm2cai2/commit/9b50349f1e6363655511afccaef26a7c1c10bf60))
* updated dockerfile to include correct pgdump version for wm-backup command ([dd728c6](https://github.com/webmappsrl/osm2cai2/commit/dd728c68e509941bd44d15d0a507c147bbb52bee))

## [1.1.0](https://github.com/webmappsrl/osm2cai2/compare/v1.0.0...v1.1.0) (2025-04-22)


### Features

* add monitoring data retrieval oc: 5274 ([#168](https://github.com/webmappsrl/osm2cai2/issues/168)) ([419a152](https://github.com/webmappsrl/osm2cai2/commit/419a152c0dc9000f132d59818dd281cba34ba678))
* Added new tests for GeometryService oc:4553 ([#157](https://github.com/webmappsrl/osm2cai2/issues/157)) ([6ed2fac](https://github.com/webmappsrl/osm2cai2/commit/6ed2face0b4b676e30333835492f0b8ef7985806))
* Added some tests for HikingRouteDescriptionService oc:4554 ([#158](https://github.com/webmappsrl/osm2cai2/issues/158)) ([4d720c9](https://github.com/webmappsrl/osm2cai2/commit/4d720c927a14f05e2beb8cf12d20da18722c1f72))
* Added tests for AreaModelService and factory for Area oc:4552 ([#155](https://github.com/webmappsrl/osm2cai2/issues/155)) ([e88660d](https://github.com/webmappsrl/osm2cai2/commit/e88660da089aa373520c5dec9a9b216e34069a3c))
* Added tests for OsmService oc:4555 ([#161](https://github.com/webmappsrl/osm2cai2/issues/161)) ([43eb198](https://github.com/webmappsrl/osm2cai2/commit/43eb198bd214088af7695b9538b7425e3396750e))
* Enhance OSM2CAI user synchronization with advanced filtering and debugging options ([8b5057c](https://github.com/webmappsrl/osm2cai2/commit/8b5057c6153e911fe9058407167de1164e2854b3))


### Bug Fixes

* add compatibility to nova 5 for geometric fields ([fdc4ec5](https://github.com/webmappsrl/osm2cai2/commit/fdc4ec568d88eeaf093dab0abf9a5a72ee4a36a7))
* add compatibility to nova 5 oc 5080 ([35329d8](https://github.com/webmappsrl/osm2cai2/commit/35329d8ae1efc34f5854a0af383ff0b3e4980de1))
* duplicated method getRawData() ([827b7e4](https://github.com/webmappsrl/osm2cai2/commit/827b7e43a6807257201d15372f8ba7432fce0a81))
* improve geometry extraction strategy for UGC media coordinates ([5cd5a05](https://github.com/webmappsrl/osm2cai2/commit/5cd5a05e8b002a4bee527b629bcc2c23841f5994))
* nova compatibility with geometries field oc:5080 ([7fdfba2](https://github.com/webmappsrl/osm2cai2/commit/7fdfba2fc983324f90f289dcdcb364dd22a94df1))
* nova global search for hiking routes, provinces and cai huts ([b2ccf74](https://github.com/webmappsrl/osm2cai2/commit/b2ccf7484fc4f2130f67e3e78534777da0b92676))
* osmfeatures_data in update hiking route command OC:5336 ([6012f84](https://github.com/webmappsrl/osm2cai2/commit/6012f843b3e4cf19a13b969f033d339116214e15))
* resolve minor syntax and formatting issues in UGC sync command ([77e6d3e](https://github.com/webmappsrl/osm2cai2/commit/77e6d3e6a08b9fbae2293a524ada91248a7d1f7f))


### Miscellaneous Chores

* Add ResetIdSequenceCommand to reset ID sequences in specified tables ([1a3c0a0](https://github.com/webmappsrl/osm2cai2/commit/1a3c0a0a97d0203c2732c335a2a7c6d2e0aacd60))
* removed unused patch ([31561cf](https://github.com/webmappsrl/osm2cai2/commit/31561cf2da49e79fed96b6d4beae9c6aa43a4901))

## 1.0.0 (2025-04-15)


### Features

* add monitoring data retrieval oc: 5274 ([#168](https://github.com/webmappsrl/osm2cai2/issues/168)) ([419a152](https://github.com/webmappsrl/osm2cai2/commit/419a152c0dc9000f132d59818dd281cba34ba678))
* Added new tests for GeometryService oc:4553 ([#157](https://github.com/webmappsrl/osm2cai2/issues/157)) ([6ed2fac](https://github.com/webmappsrl/osm2cai2/commit/6ed2face0b4b676e30333835492f0b8ef7985806))
* Added some tests for HikingRouteDescriptionService oc:4554 ([#158](https://github.com/webmappsrl/osm2cai2/issues/158)) ([4d720c9](https://github.com/webmappsrl/osm2cai2/commit/4d720c927a14f05e2beb8cf12d20da18722c1f72))
* Added tests for AreaModelService and factory for Area oc:4552 ([#155](https://github.com/webmappsrl/osm2cai2/issues/155)) ([e88660d](https://github.com/webmappsrl/osm2cai2/commit/e88660da089aa373520c5dec9a9b216e34069a3c))
* Added tests for OsmService oc:4555 ([#161](https://github.com/webmappsrl/osm2cai2/issues/161)) ([43eb198](https://github.com/webmappsrl/osm2cai2/commit/43eb198bd214088af7695b9538b7425e3396750e))
* Enhance OSM2CAI user synchronization with advanced filtering and debugging options ([8b5057c](https://github.com/webmappsrl/osm2cai2/commit/8b5057c6153e911fe9058407167de1164e2854b3))
* enhanced ugc media sync ([df82a49](https://github.com/webmappsrl/osm2cai2/commit/df82a490e8b6a297f54a34a37541a298bc23e4d2))
* enhanced ugc media sync ([0ffa4e4](https://github.com/webmappsrl/osm2cai2/commit/0ffa4e43e6b600bae0cf8de5023f95ed9d8c3131))


### Bug Fixes

* add compatibility to nova 5 for geometric fields ([fdc4ec5](https://github.com/webmappsrl/osm2cai2/commit/fdc4ec568d88eeaf093dab0abf9a5a72ee4a36a7))
* add compatibility to nova 5 oc 5080 ([35329d8](https://github.com/webmappsrl/osm2cai2/commit/35329d8ae1efc34f5854a0af383ff0b3e4980de1))
* improve geometry extraction strategy for UGC media coordinates ([5cd5a05](https://github.com/webmappsrl/osm2cai2/commit/5cd5a05e8b002a4bee527b629bcc2c23841f5994))
* nova compatibility with geometries field oc:5080 ([7fdfba2](https://github.com/webmappsrl/osm2cai2/commit/7fdfba2fc983324f90f289dcdcb364dd22a94df1))
* resolve minor syntax and formatting issues in UGC sync command ([77e6d3e](https://github.com/webmappsrl/osm2cai2/commit/77e6d3e6a08b9fbae2293a524ada91248a7d1f7f))


### Miscellaneous Chores

* Add ResetIdSequenceCommand to reset ID sequences in specified tables ([1a3c0a0](https://github.com/webmappsrl/osm2cai2/commit/1a3c0a0a97d0203c2732c335a2a7c6d2e0aacd60))
* added pre-sync script ([a2ec208](https://github.com/webmappsrl/osm2cai2/commit/a2ec208992b7966f82e9f23889a3ce4020fb82c7))
* removed unused patch ([31561cf](https://github.com/webmappsrl/osm2cai2/commit/31561cf2da49e79fed96b6d4beae9c6aa43a4901))
* updated queries ([e588b0b](https://github.com/webmappsrl/osm2cai2/commit/e588b0b49e12bc78180094f7603a2a120ff4e9aa))
* updated queries ([796eaa1](https://github.com/webmappsrl/osm2cai2/commit/796eaa1b3aada2b2ca4c7eb58cdcb8d68546692d))

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
