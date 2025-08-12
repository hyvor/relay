<?php

namespace App\Tests\Factory;

use App\Entity\Domain;
use App\Entity\Type\DomainStatus;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;

/**
 * @extends PersistentProxyObjectFactory<Domain>
 */
final class DomainFactory extends PersistentProxyObjectFactory
{

    public function __construct()
    {
        parent::__construct();
    }

    public static function class(): string
    {
        return Domain::class;
    }

    /**
     * @return array<string, mixed>
     */
    protected function defaults(): array
    {
        return [
            'project' => ProjectFactory::createOne(),
            'domain' => self::faker()->unique()->domainName(),
            'status' => DomainStatus::PENDING,
            'status_changed_at' => \DateTimeImmutable::createFromMutable(self::faker()->dateTime()),
            'dkim_selector' => self::faker()->text(255),
            'dkim_public_key' => self::TEST_DKIM_PUBLIC_KEY,
            'dkim_private_key_encrypted' => self::TEST_DKIM_PRIVATE_KEY_ENCRYPTED,
            'created_at' => \DateTimeImmutable::createFromMutable(self::faker()->dateTime()),
            'updated_at' => \DateTimeImmutable::createFromMutable(self::faker()->dateTime()),
        ];
    }

    public const string TEST_DKIM_PUBLIC_KEY = '-----BEGIN PUBLIC KEY-----
MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEAq0N5I5gsoG0E/R97DAi5
UFprqDutIAt3Yak4d+ofDUnuQ54QoA1mDTuiz6bbqD60tzJ/08VU2l/HlwMQ8xSc
Ps49EQ+0wh6WCDft4TXEm58VPhYmMXuZl7Zh2IBX3nLEORCrVLcZkadcM95Cp5fa
YARFhyfJsWPI7FS/P4is22tKuQGJDqqPi5Rkz/3ZHrVQ92ttn2fzxFNkwV+ukn/N
1N0/OrbdaX2wOfhAAsHQq7ShNSEdOwSUCcyE4sWBmuVf5hroFP0cqWWhTK3kQ0ju
BXDGpsckTbge6LpW6yTZRMGcXow1NU8zgIY4cz4B8FyIGD0+ghC8uLHl2R4TSHAj
zwIDAQAB
-----END PUBLIC KEY-----';

    public const string TEST_DKIM_PRIVATE_KEY_ENCRYPTED = 'eyJpdiI6IktSb05rODVkUFNIc1dWN2IrUWZ1cWc9PSIsInZhbHVlIjoiR2VXeW5YSStTeURHTW9pTEphclZUZEt4Z1VxZ0NiUTdaY2NoNVFUWTRYQ0taZUxyMGcvU21KY1hvWWJvc2JUeURjNUVkTGp0T2F0REh3ZGgya0o1YjdGWW1Wanh2bGh3MXZJRWNqZmJkSFdKNERJZk92S2xQZHdSaElIaVY5a3FLMFgxak1LSC8ydExLTXJrMXNSaWRUNCtYUWFjdkhwVXB5NU5JQVo3WnFXS1ZwOEl4enZTL2NISmlEcWJPN3lzUG9QTll0a1p6NXYrL2VvcmdLUmNWRGdKM1pZZU1BQkppeEx6eGJtQWpTTWg0UE9JZHg1b0pFWUhGWXQwUUo2dTdpem5oQm9tWTZycXRqN0xrZWtDamdBbGlodTRUT2RwMDBFQ1Vwd1B5OVhtbFlCVTVla1N6OElqS3NGeWJaTmhtYW1ra0tXWjhWaFRTTTVEZmpmS01Mb0Y4bmZrUWtDVFRrZkN1MW1pa1lDZXpVbUVYeU9iYlduN1FxSUZ0N1lvMHp5ek9xVjFXSGg0dzYvdEwyQXB0K1Zhd0Nra2grNENIL2EydnJLNXZKdDNobXdId0R6T1lYR2U1NElYVE8zWDFjbDFxR3pQV094SzA0a2NtaHZBQWtZZk0raWZWdisyM1JnbENIWkpLd1hTQkRtSVF1RjNhNUVwQ3BrSHNwbFVvY0x3YnllMHJmOHJsd2JjcmJhWkY0ckdjNktjc3JOVlhubHNWMUV5K09vYktuejZQOTEvTGphcWlTQ2VHWjZqLzl5c0R0dW5ZVnpsNWJlN2ZDU1RiTGsxNGJzTklGa1dORllnMVhtZW9ZTDVqMDl4VVVNakxiVVRQbnp2YzZ2TEUzaEVKV0hSVnBqdGNlWGErVXNZbDA5Z2hSMllJUXA1TXdxcGYxMjNVWDQyNmd2bThZNG9SZk9pUWI1QlZjUHczTlZCOU80NXI0a1lxSktMUTFUTVhKdDhoejlWc21WOWNoMy9FcW1obVhtajNMWEVZend1dGtlRWtBTzEyNTJNRlQzdUZjQUFiMEQ0ZVNNOUVQKzFPbXVEUE5EYnFvamZJZjVjd0Y0ajZvN1ZyaFRaQjQrT3NWQmpWNWpha3F0bkltUWZSTXZ5RXFzWjZyZHp2T3VKbkxwbHJiV1pjbXIwMmVFaG9aNkgyc09oVlpFandVY1RWaDZaSENRTmtQM2ZOM3pwTGZ2V0JVano4SkNjVkxtWHBqSHF0bUtQMEhzdDRxYUc0VGFhd2orbmVESXJOZTdpeTRaOTFlYUVVWXJxbnV1VkRXU0VJclZJV2xUYlFOajNFQUpPcmc1bm9TQk9USE9oQU1LVmZZYnVMd3hITkhJRFRhcnRBTHN6TGhLS0srTFRvSzRZR1p6YmVvZFYrZDRKaEkxUm5FM29qend3em5LN29hb3lsaWV0WkRLMXJYSHBwbHB4aEMyTjNjMCtyMCtYalV6SHEzK3lES0RNcUlLTDBhY0VZRGlrWm0zaTl0THV1WXpaWjY5Z2lwYWlFM0ZQcnNtQ08xWDNuRkljOGk2aEp2enRlb2JIVXFZdnI4SmJSWm4yNW1ER2lTMVExckw5Tlg5Q3U1ZlNzbnM3UGEyR1BudWNnZ2RWWXhxMUlDOCtFbEd4c0swYVlweUtkOFVGWXFYVEVudXg5T1M4Q1FwUWcwdEw0WmkzSDYyUkFwNGNGdElOdXBJSGJnRzJLS0taRW9pWTJGa2pjK0xRY3Vac0d6TmJpYzdnS0RPQ282RnZYdHZ3S2xmRWRaQWdDTDJRQlJRNDdFL2FqV2RJQ0hmczh0WWpmYkg3UzNOaVA2NXNmK1l6K0hIVlNhN21nV2FWU2hNWCtwb1ptWUVSTk1VRm8rckdadFFWcEZwQ1QwbkxSWG5wWnhObkt1c0pRejhmY3lSSU8wd29uLzhlT1FKSmpDVC9HSDRQY2cwVnd4MzQ3VHRRdGFQL1RQaFZoWExTamhsZHVUVlc3d2ZSQWJCeGNieFNRVENsZ2NTOTk1TU1WdWNpajAxZ2ZENDBBYkhzUi9sRmpNT3E1VDZxZWJzTi8wSjMvVXJqdVN4SmZFa1JudDlHSkN6VkNmOGVzY1RLWVVLOTRBTWpkV0s5MzFZdlJjdHVqckVxUjdUQkhBYUVxRDRXOUN2LzFGMWRrM2JucFFWUmticEkvc25HUmZnTTZrbElJVEppS3JSbmhUZ3MrcVhjSVZWd2tlZDA3aTEvbFhFN3RVSHVhQXpEMmFaa2lkMlVYdkdGRFp2N3lPMWFKN0lTTFNVQU1vOGpvSjlML2dqeWZoaWpEYk94VmtieTN5WmhNWlo3YUFoS0hxTllpd1J5WHQyS2h6Y0pDZXlRcHNNakFVRDlLMU5TS1l0YzlKZGN1a3h4MmxiWFArZGRPQ2E1TkZ3YXBydzliTjBGNmNCK29GZ0Z3WXlVRkZUTEZ0WDV2QTlxamJxZVY4dFRMNmVxMjlWckNkbGdRNm5nWWlhTFlIWjhhdjJXVHRPb2d6YnNNbjhWYThOUGUyd3FmTDdKbUROSThtRnJJRGoxMjdiZzAreHo2cHVwZitadlVuN2tDVkhCV0lhaTRDZWJTUTZHTnRQYWkzQnJtc0g1bmJvMkduTDc2TnRvdHh3aHlJcWdoMzQ0RitqdmRGakJZVFVoaFkyemdjV2l0VGxqNndvZUJiRHJibGlaeVhtRStVNkJRSDNnUFpmWkNmTjdnZ2JySHp2UXZaNFFXRHg4aFM2MlBZSGJYWVR2TUpMTE9rTGlGcmY3QWk2MjNleWx1M0kydVFKd2VXL01LUzNrQlp5Uk56Qloyd0twczBUcmYwclI0T2x4V2hLbDVBSTlaUTN0MG0ydHUxWFJwK0V4THh1QXlnZDlYQjIwQnN3MEh4cnJEb001U2ZvZGVSZTBxUDJzTmxQWXUxaXFTQVNtOEduWVNWMW1tS2RUcjB1Q002QTVNdHBBcFR1K1lMYnlaRHY4bSt4UUlacjg5b1Jhemd2U0RFdXZ0aHBUcGhGN05XbDdpZVcxY1BxSWVQdFJrS0ZpNVZjPSIsIm1hYyI6IjU0NjNlY2Q4MDNmNTBjOTBkZmFmNjBjMDM2NDI1NjUwMzg3NDc0ZmVkZjcyZWUyNWRhZmM5ZmU4MzE1NGMxZTciLCJ0YWciOiIifQ==';

}
