<script setup>
import { ref } from 'vue';
import { Link, router, useForm } from '@inertiajs/vue3';

const props = defineProps({
    savedSearch: { type: Object, required: true },
    alertFrequencies: { type: Array, required: true },
});

// Only the name and the frequency can change. The criteria are fixed once
// saved, so they are shown but never edited here.
const form = useForm({
    name: props.savedSearch.name ?? '',
    alert_frequency: props.savedSearch.alert_frequency,
});

function save() {
    form.patch(`/saved-searches/${props.savedSearch.id}`, { preserveScroll: true });
}

const deleting = ref(false);

function destroy() {
    // Deleting also stops the alerts and cannot be undone, so ask first.
    if (!window.confirm('Delete this saved search? Its alerts will stop.')) {
        return;
    }

    router.delete(`/saved-searches/${props.savedSearch.id}`, {
        preserveScroll: true,
        onStart: () => (deleting.value = true),
        onFinish: () => (deleting.value = false),
    });
}

const fieldClasses =
    'rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm focus:border-slate-900 focus:outline-none focus:ring-1 focus:ring-slate-900';
</script>

<template>
    <article class="rounded-xl border border-slate-200 bg-white p-4">
        <h2 class="font-semibold">{{ savedSearch.summary }}</h2>

        <form class="mt-3 flex flex-wrap items-end gap-3" @submit.prevent="save">
            <div class="flex flex-col gap-1">
                <label :for="`name-${savedSearch.id}`" class="text-xs font-medium text-slate-600">
                    Name (optional)
                </label>
                <input
                    :id="`name-${savedSearch.id}`"
                    v-model="form.name"
                    type="text"
                    maxlength="100"
                    placeholder="e.g. Leeds flats"
                    :class="[fieldClasses, 'w-64']"
                />
            </div>

            <div class="flex flex-col gap-1">
                <label
                    :for="`alert-frequency-${savedSearch.id}`"
                    class="text-xs font-medium text-slate-600"
                >
                    Alert me
                </label>
                <select
                    :id="`alert-frequency-${savedSearch.id}`"
                    v-model="form.alert_frequency"
                    :class="fieldClasses"
                >
                    <option
                        v-for="frequency in alertFrequencies"
                        :key="frequency.value"
                        :value="frequency.value"
                    >
                        {{ frequency.label }}
                    </option>
                </select>
            </div>

            <button
                type="submit"
                :disabled="form.processing || !form.isDirty"
                class="rounded-lg bg-slate-900 px-5 py-2 text-sm font-medium text-white transition hover:bg-slate-700 disabled:opacity-50 disabled:hover:bg-slate-900"
            >
                Save
            </button>

            <span v-if="form.recentlySuccessful" class="py-2 text-sm text-slate-500" role="status">
                Saved.
            </span>
        </form>

        <ul v-if="form.hasErrors" role="alert" class="mt-2 text-sm text-red-700">
            <li v-for="(message, field) in form.errors" :key="field">{{ message }}</li>
        </ul>

        <div class="mt-4 flex items-center justify-between gap-4 border-t border-slate-100 pt-3">
            <Link
                href="/"
                :data="savedSearch.filters"
                class="text-sm font-medium text-slate-900 underline-offset-2 hover:underline"
            >
                View results
            </Link>

            <button
                type="button"
                :disabled="deleting"
                class="text-sm text-red-700 underline-offset-2 hover:underline disabled:opacity-50"
                @click="destroy"
            >
                Delete
            </button>
        </div>
    </article>
</template>
