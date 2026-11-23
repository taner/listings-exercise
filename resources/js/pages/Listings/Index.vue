<script setup>
import { computed, ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AppLayout from '../../components/AppLayout.vue';
import ListingCard from '../../components/ListingCard.vue';
import ListingFilters from '../../components/ListingFilters.vue';
import Pagination from '../../components/Pagination.vue';
import { withoutBlanks } from '../../filters';

const props = defineProps({
    listings: { type: Object, required: true },
    branches: { type: Array, required: true },
    propertyTypes: { type: Array, required: true },
    filters: { type: Object, required: true },
    // Shared by Inertia on every page. A rejected search redirects back here
    // with the reasons, and without showing them it looks as if nothing happened.
    errors: { type: Object, default: () => ({}) },
});

const processing = ref(false);

// Seeded from the server so the filter form survives a refresh, a shared link
// or the back button — the query string is the single source of truth.
const form = ref({
    property_type: props.filters.property_type ?? '',
    region: props.filters.region ?? '',
    min_bedrooms: props.filters.min_bedrooms ?? '',
    max_bedrooms: props.filters.max_bedrooms ?? '',
    min_bathrooms: props.filters.min_bathrooms ?? '',
    min_price: props.filters.min_price ?? '',
    max_price: props.filters.max_price ?? '',
});

const saving = ref(false);

// What gets saved is the search the results on screen came from, not whatever
// is half-typed in the form, so it reads the applied filters from the server.
const appliedFilters = computed(() => withoutBlanks(props.filters));

function saveSearch() {
    router.post('/saved-searches', appliedFilters.value, {
        onStart: () => (saving.value = true),
        onFinish: () => (saving.value = false),
    });
}

function search() {
    router.get('/', withoutBlanks(form.value), {
        preserveState: true,
        preserveScroll: true,
        replace: true,
        // Inertia cancels the in-flight visit when a new one starts, so rapid
        // searches can't land out of order.
        onStart: () => (processing.value = true),
        onFinish: () => (processing.value = false),
    });
}

function reset() {
    form.value = {
        property_type: '',
        region: '',
        min_bedrooms: '',
        max_bedrooms: '',
        min_bathrooms: '',
        min_price: '',
        max_price: '',
    };
    search();
}
</script>

<template>
    <Head title="Properties for sale" />

    <AppLayout heading="Properties for sale" subheading="Across our branches.">
        <ListingFilters
            v-model="form"
            :branches="branches"
            :property-types="propertyTypes"
            :processing="processing"
            @submit="search"
            @reset="reset"
        />

        <ul
            v-if="Object.keys(errors).length"
            role="alert"
            class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700"
        >
            <li v-for="(message, field) in errors" :key="field">{{ message }}</li>
        </ul>

        <div class="mb-4 flex items-center justify-between gap-4">
            <p class="text-sm text-slate-500" aria-live="polite">
                <span v-if="processing">Loading…</span>
                <span v-else>{{ listings.meta.total }} listing(s) found</span>
            </p>

            <button
                type="button"
                :disabled="saving || Object.keys(appliedFilters).length === 0"
                class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium transition hover:bg-slate-100 disabled:opacity-50 disabled:hover:bg-white"
                @click="saveSearch"
            >
                Save this search
            </button>
        </div>

        <div
            v-if="listings.data.length"
            class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3"
            :class="processing && 'opacity-50 transition'"
        >
            <ListingCard
                v-for="listing in listings.data"
                :key="listing.id"
                :listing="listing"
            />
        </div>

        <p
            v-else
            class="rounded-xl border border-dashed border-slate-300 p-10 text-center text-slate-500"
        >
            No listings match those filters.
        </p>

        <Pagination :meta="listings.meta" :links="listings.links" />
    </AppLayout>
</template>
