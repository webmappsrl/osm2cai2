<template>
    <panel-item :field="field">
        <template #value>
        <osm2cai-map-multi-linestring :field="field"></osm2cai-map-multi-linestring>
        </template>
    </panel-item>
</template>

<script>
export default {
    props: ['index','resource', 'resourceName', 'resourceId', 'field'],
}
</script>
