<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '../../components/AppLayout.vue';
import SavedSearchCard from '../../components/SavedSearchCard.vue';

defineProps({
    savedSearches: { type: Array, required: true },
    alertFrequencies: { type: Array, required: true },
});
</script>

<template>
    <Head title="Saved searches" />

    <AppLayout
        heading="Saved searches"
        subheading="Get an alert when something new matches."
    >
        <div v-if="savedSearches.length" class="grid gap-4">
            <SavedSearchCard
                v-for="savedSearch in savedSearches"
                :key="savedSearch.id"
                :saved-search="savedSearch"
                :alert-frequencies="alertFrequencies"
            />
        </div>

        <p
            v-else
            class="rounded-xl border border-dashed border-slate-300 p-10 text-center text-slate-500"
        >
            You have no saved searches yet. Filter the
            <Link href="/" class="font-medium text-slate-900 underline-offset-2 hover:underline">listings</Link>
            and choose "Save this search".
        </p>
    </AppLayout>
</template>
