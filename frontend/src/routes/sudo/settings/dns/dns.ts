import { get } from "svelte/store";
import type { DnsRecord } from "../../sudoTypes";
import { instanceStore } from "../../sudoStore";

export function getHost(subdomain: string) {
    const subdomainPart = subdomain ? `${subdomain}.` : '';
    const instanceDomain = get(instanceStore).domain;
    return `${subdomainPart}${instanceDomain}`;
}