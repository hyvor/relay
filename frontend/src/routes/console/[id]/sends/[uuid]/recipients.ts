import type { SendRecipient } from "../../../types";

export function getSortedRecipients(recipients: SendRecipient[]) {

    const r = [...recipients];
    return r.sort((a, b) => {
        const typeOrder = { to: 0, cc: 1, bcc: 2 };
        if (typeOrder[a.type] !== typeOrder[b.type]) {
            return typeOrder[a.type] - typeOrder[b.type];
        }
        const aDomain = a.address.split('@')[1] || '';
        const bDomain = b.address.split('@')[1] || '';

        return aDomain.localeCompare(bDomain);
    });

}