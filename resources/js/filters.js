// Blank fields are dropped rather than sent empty, so a listings URL only ever
// carries the filters that are actually set.
export function withoutBlanks(filters) {
    return Object.fromEntries(
        Object.entries(filters).filter(([, value]) => value !== '' && value !== null),
    );
}
