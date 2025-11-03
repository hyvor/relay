export function formatCheckName(key: string): string {
    return {
        all_queues_have_at_least_one_ip: 'All queues have at least one IP',
        all_active_ips_have_correct_ptr:
            'All active IPs have correct PTR records (Forward and Reverse)',
        instance_dkim_correct: 'Instance DKIM is correct',
        all_ips_are_in_spf_record: 'All IPs are included in SPF record',
        none_of_the_ips_are_on_known_blacklists: 'None of the IPs are on known blacklists',
        no_unread_infrastructure_bounces: 'No unread infrastructure bounces'
    }[key]!;
}
