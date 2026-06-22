<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DevDatabaseSeeder extends Seeder
{
    public function run(): void
    {

        $this->copy('settings', [
            'id',
            'key',
            'value',
            'created_at',
            'updated_at'
        ], <<<'DATA'
1	mail_enabled	1	2026-04-11 16:03:09	2026-04-11 16:03:09
3	mail_port	587	2026-04-11 16:03:09	2026-04-11 16:03:09
7	mail_from_name	AD-PKI	2026-04-11 16:03:09	2026-04-11 16:03:09
9	webhook_enabled	0	2026-04-11 16:03:09	2026-04-11 16:03:09
12	webhook_secret	\N	2026-04-11 16:03:09	2026-04-11 16:03:09
13	telegram_enabled	0	2026-04-11 16:03:09	2026-04-11 16:03:09
2	mail_host	smtp.strato.de	2026-04-11 16:03:09	2026-04-11 16:33:49
4	mail_username	support@danakiran.de	2026-04-11 16:03:09	2026-04-11 16:33:49
5	mail_password	&8KM2eeZ6XPPF#6kzQzU	2026-04-11 16:03:09	2026-04-11 16:33:49
6	mail_from_email	support@danakiran.de	2026-04-11 16:03:09	2026-04-11 16:33:49
8	mail_encryption	tls	2026-04-11 16:03:09	2026-04-11 16:49:53
11	webhook_method	PUT	2026-04-11 16:03:09	2026-04-12 09:09:02
14	telegram_bot_token	7723656744:AAFA8t_L5fC4D7Yap03HBePR2uYM-8bMlCk	2026-04-11 16:03:09	2026-04-12 09:09:02
15	telegram_chat_id	8400793770	2026-04-11 16:03:09	2026-04-12 09:09:02
10	webhook_url	\N	2026-04-11 16:03:09	2026-04-12 09:39:01
16	crl_base_url	http://127.0.0.1:8000	\N	\N
17	ocsp_base_url	http://127.0.0.1:8080	\N	\N
DATA);

        $this->copy('users', [
            'id',
            'username',
            'firstname',
            'lastname',
            'email',
            'email_verified_at',
            'password',
            'role_id',
            'remember_token',
            'created_at',
            'updated_at'
        ], <<<'DATA'
11	ali	ali	ali	ali@ali.com	\N	$2y$12$ciAVefmV.rxy1DerwkHYdO490oECvK47IpW.fUJm7rYSRDTSrdWe6	4	\N	2026-04-12 16:27:18	2026-05-07 06:44:23
1	admin	Ali	Admin	ali@ali.de	\N	$2y$12$bTZb03kVTk27yYsw6tupaOofna1lv/l0wSKzO7vCg2xcg0JUkN27C	1	\N	2026-04-11 15:38:30	2026-05-07 07:20:04
DATA);


        $this->copy('certificates', [
            'id',
            'type',
            'common_name',
            'san',
            'serial_number',
            'valid_from',
            'valid_to',
            'parent_id',
            'is_acme',
            'crt_path',
            'key_path',
            'chain_path',
            'crl_path',
            'created_at',
            'updated_at',
            'revoked',
            'revoked_at',
            'revocation_reason',
            'key_type',
            'key_size',
            'curve',
            'status',
            'requested_by',
            'approved_by',
            'approved_at',
            'rejected_by',
            'rejected_at',
            'rejection_reason',
            'request_data'
        ], <<<'DATA'
1	root	Test Root CA	\N	507267492069F768AC5CD7F75C720D7DF99DDFC0	2026-04-11 14:28:01	2036-04-11 14:28:01	\N	f	/var/lib/adpki/root/root.crt	\N	\N	\N	2026-04-11 14:28:01	2026-04-11 14:28:01	f	\N	\N	\N	\N	\N	issued	\N	\N	\N	\N	\N	\N	\N
2	intermediate	Test Intermediate CA	\N	7DF776CE0CDED6203F4B9ADBD86FBB3FF4AFF889	2025-12-25 15:15:07	2030-12-24 15:15:07	1	f	/var/lib/adpki/intermediates/int-2/intermediate.crt	/var/lib/adpki/intermediates/int-2/private/intermediate.key	\N	/crl/int-2.pem	2026-04-11 14:28:04	2026-04-11 14:28:04	f	\N	\N	\N	\N	\N	issued	\N	\N	\N	\N	\N	\N	\N
3	tls	teste	\N	bbd15762b452e90	2026-04-12 12:17:20	2026-04-17 14:33:57	2	f	/var/lib/adpki/issued/teste-bbd15762b452e90/certificate.crt	/var/lib/adpki/issued/teste-bbd15762b452e90/private.key	/var/lib/adpki/issued/teste-bbd15762b452e90/fullchain.pem	\N	2026-04-12 10:17:20	2026-04-12 10:23:01	t	2026-04-12 10:23:01	unspecified	\N	\N	\N	issued	\N	\N	\N	\N	\N	\N	\N
4	tls	Eventtest	\N	332220c7ff72f459	2026-04-12 14:35:44	2026-04-17 14:36:29	2	f	/var/lib/adpki/issued/Eventtest-332220c7ff72f459/certificate.crt	/var/lib/adpki/issued/Eventtest-332220c7ff72f459/private.key	/var/lib/adpki/issued/Eventtest-332220c7ff72f459/fullchain.pem	\N	2026-04-12 12:35:43	2026-04-12 12:42:46	t	2026-04-12 12:42:46	unspecified	\N	\N	\N	issued	\N	\N	\N	\N	\N	\N	\N
5	tls	tester	\N	2db96a07a5f671f2	2026-04-12 14:43:54	2027-04-12 14:43:54	2	f	/var/lib/adpki/issued/tester-2db96a07a5f671f2/certificate.crt	/var/lib/adpki/issued/tester-2db96a07a5f671f2/private.key	/var/lib/adpki/issued/tester-2db96a07a5f671f2/fullchain.pem	\N	2026-04-12 12:43:54	2026-04-12 12:45:35	t	2026-04-12 12:45:35	unspecified	\N	\N	\N	issued	\N	\N	\N	\N	\N	\N	\N
6	tls	alievent	\N	32531f4a4df4b4c7	2026-04-12 14:46:03	2027-04-12 14:46:03	2	f	/var/lib/adpki/issued/alievent-32531f4a4df4b4c7/certificate.crt	/var/lib/adpki/issued/alievent-32531f4a4df4b4c7/private.key	/var/lib/adpki/issued/alievent-32531f4a4df4b4c7/fullchain.pem	\N	2026-04-12 12:46:03	2026-04-12 12:47:38	t	2026-04-12 12:47:38	unspecified	\N	\N	\N	issued	\N	\N	\N	\N	\N	\N	\N
7	tls	testerzhgb	\N	3f1ba51294514717	2026-04-12 14:46:53	2027-04-12 14:46:53	2	f	/var/lib/adpki/issued/testerzhgb-3f1ba51294514717/certificate.crt	/var/lib/adpki/issued/testerzhgb-3f1ba51294514717/private.key	/var/lib/adpki/issued/testerzhgb-3f1ba51294514717/fullchain.pem	\N	2026-04-12 12:46:53	2026-04-12 12:49:38	t	2026-04-12 12:49:38	key_compromise	\N	\N	\N	issued	\N	\N	\N	\N	\N	\N	\N
8	tls	test-code	["test-codde.de"]	1ba092e28fc5a892	2026-04-22 11:03:58	2027-04-22 11:03:58	2	f	/var/lib/adpki/issued/test-code-1ba092e28fc5a892/certificate.crt	/var/lib/adpki/issued/test-code-1ba092e28fc5a892/private.key	/var/lib/adpki/issued/test-code-1ba092e28fc5a892/fullchain.pem	\N	2026-04-22 09:03:58	2026-04-22 09:03:58	f	\N	\N	\N	\N	\N	issued	\N	\N	\N	\N	\N	\N	\N
9	codesign	Test-Software-Code	\N	28ba3001cfe91e1e	2026-04-22 11:05:44	2027-04-22 11:05:44	2	f	/var/lib/adpki/issued/Test-Software-Code-28ba3001cfe91e1e/certificate.crt	/var/lib/adpki/issued/Test-Software-Code-28ba3001cfe91e1e/private.key	/var/lib/adpki/issued/Test-Software-Code-28ba3001cfe91e1e/fullchain.pem	\N	2026-04-22 09:05:44	2026-04-22 09:05:44	f	\N	\N	\N	\N	\N	issued	\N	\N	\N	\N	\N	\N	\N
10	tls	testyp	\N	265188dec26ccc12	2026-04-22 11:21:02	2027-04-22 11:21:02	2	f	/var/lib/adpki/issued/testyp-265188dec26ccc12/certificate.crt	/var/lib/adpki/issued/testyp-265188dec26ccc12/private.key	/var/lib/adpki/issued/testyp-265188dec26ccc12/fullchain.pem	\N	2026-04-22 09:21:02	2026-04-22 09:21:02	f	\N	\N	\N	\N	\N	issued	\N	\N	\N	\N	\N	\N	\N
11	tls	test.local	\N	1918d5c3f372cfc9	2026-04-22 11:21:17	2027-04-22 11:21:17	2	f	/var/lib/adpki/issued/test.local-1918d5c3f372cfc9/certificate.crt	\N	/var/lib/adpki/issued/test.local-1918d5c3f372cfc9/fullchain.pem	\N	2026-04-22 09:21:17	2026-04-22 09:21:17	f	\N	\N	\N	\N	\N	issued	\N	\N	\N	\N	\N	\N	\N
12	codesign	test.local	\N	a3778c6f144eda0	2026-04-22 12:10:28	2027-04-22 12:10:28	2	f	/var/lib/adpki/issued/test.local-a3778c6f144eda0/certificate.crt	\N	/var/lib/adpki/issued/test.local-a3778c6f144eda0/fullchain.pem	\N	2026-04-22 10:10:28	2026-04-22 10:10:28	f	\N	\N	\N	\N	\N	issued	\N	\N	\N	\N	\N	\N	\N
13	client	Max Mustermann	\N	14129a1796b03ab	2026-04-22 12:21:56	2027-04-22 12:21:56	2	f	/var/lib/adpki/issued/Max_Mustermann-14129a1796b03ab/certificate.crt	\N	/var/lib/adpki/issued/Max_Mustermann-14129a1796b03ab/fullchain.pem	\N	2026-04-22 10:21:56	2026-04-22 10:21:56	f	\N	\N	\N	\N	\N	issued	\N	\N	\N	\N	\N	\N	\N
14	tls	test-ecdsa	["test-ecdsa.de"]	3729ecb432c35c35	2026-04-22 13:53:28	2027-04-22 13:53:28	2	f	/var/lib/adpki/issued/test-ecdsa-3729ecb432c35c35/certificate.crt	/var/lib/adpki/issued/test-ecdsa-3729ecb432c35c35/private.key	/var/lib/adpki/issued/test-ecdsa-3729ecb432c35c35/fullchain.pem	\N	2026-04-22 11:53:28	2026-04-22 11:53:28	f	\N	\N	\N	\N	\N	issued	\N	\N	\N	\N	\N	\N	\N
15	tls	ecdsa	["edcsa.test"]	fcff0b2fc23db73	2026-04-22 13:58:00	2027-04-22 13:58:00	2	f	/var/lib/adpki/issued/ecdsa-fcff0b2fc23db73/certificate.crt	/var/lib/adpki/issued/ecdsa-fcff0b2fc23db73/private.key	/var/lib/adpki/issued/ecdsa-fcff0b2fc23db73/fullchain.pem	\N	2026-04-22 11:58:00	2026-04-22 11:58:00	f	\N	\N	\N	\N	\N	issued	\N	\N	\N	\N	\N	\N	\N
16	tls	ed	\N	3344d067ce1715ee	2026-04-22 13:59:45	2027-04-22 13:59:45	2	f	/var/lib/adpki/issued/ed-3344d067ce1715ee/certificate.crt	/var/lib/adpki/issued/ed-3344d067ce1715ee/private.key	/var/lib/adpki/issued/ed-3344d067ce1715ee/fullchain.pem	\N	2026-04-22 11:59:45	2026-04-22 11:59:45	f	\N	\N	\N	\N	\N	issued	\N	\N	\N	\N	\N	\N	\N
17	tls	edet	\N	2ec88d0fd710dc8a	2026-04-22 14:01:48	2027-04-22 14:01:48	2	f	/var/lib/adpki/issued/edet-2ec88d0fd710dc8a/certificate.crt	/var/lib/adpki/issued/edet-2ec88d0fd710dc8a/private.key	/var/lib/adpki/issued/edet-2ec88d0fd710dc8a/fullchain.pem	\N	2026-04-22 12:01:48	2026-04-22 12:01:48	f	\N	\N	\N	\N	\N	issued	\N	\N	\N	\N	\N	\N	\N
18	tls	testerrrefrad	\N	eacfd2fc98011da	2026-04-22 14:10:59	2027-04-22 14:10:59	2	f	/var/lib/adpki/issued/testerrrefrad-eacfd2fc98011da/certificate.crt	/var/lib/adpki/issued/testerrrefrad-eacfd2fc98011da/private.key	/var/lib/adpki/issued/testerrrefrad-eacfd2fc98011da/fullchain.pem	\N	2026-04-22 12:10:59	2026-04-22 12:10:59	f	\N	\N	\N	\N	\N	issued	\N	\N	\N	\N	\N	\N	\N
19	tls	rsadfaegdfdae	\N	dbf0c78dfce9274	2026-04-22 14:14:11	2027-04-22 14:14:11	2	f	/var/lib/adpki/issued/rsadfaegdfdae-dbf0c78dfce9274/certificate.crt	/var/lib/adpki/issued/rsadfaegdfdae-dbf0c78dfce9274/private.key	/var/lib/adpki/issued/rsadfaegdfdae-dbf0c78dfce9274/fullchain.pem	\N	2026-04-22 12:14:11	2026-04-22 12:14:11	f	\N	\N	rsa	4096	\N	issued	\N	\N	\N	\N	\N	\N	\N
20	tls	ecdsafadsased	\N	268f5fc247dce71f	2026-04-22 14:14:36	2027-04-22 14:14:36	2	f	/var/lib/adpki/issued/ecdsafadsased-268f5fc247dce71f/certificate.crt	/var/lib/adpki/issued/ecdsafadsased-268f5fc247dce71f/private.key	/var/lib/adpki/issued/ecdsafadsased-268f5fc247dce71f/fullchain.pem	\N	2026-04-22 12:14:36	2026-04-22 12:14:36	f	\N	\N	ecdsa	\N	P521	issued	\N	\N	\N	\N	\N	\N	\N
21	codesign	tesr	\N	5c6484def70fb9c	2026-05-01 14:29:00	2027-05-01 14:29:00	2	f	/var/lib/adpki/issued/tesr-5c6484def70fb9c/certificate.crt	/var/lib/adpki/issued/tesr-5c6484def70fb9c/private.key	/var/lib/adpki/issued/tesr-5c6484def70fb9c/fullchain.pem	\N	2026-05-01 12:29:00	2026-05-01 12:29:00	f	\N	\N	rsa	2048	\N	issued	\N	\N	\N	\N	\N	\N	\N
26	tls	Zertifikatbeantragen	["beantragen.de"]	\N	\N	\N	\N	f	\N	\N	\N	\N	2026-05-07 11:25:14	2026-05-07 11:38:39	f	\N	\N	ecdsa	\N	P521	rejected	11	\N	\N	1	2026-05-07 11:38:39	Ne	\N
25	tls	tez	["zer.de"]	\N	\N	\N	\N	f	\N	\N	\N	\N	2026-05-07 11:17:11	2026-05-07 11:38:41	f	\N	\N	rsa	2048	\N	rejected	11	\N	\N	1	2026-05-07 11:38:41	Ne	\N
27	tls	Request	["Request.de"]	2f5027bfa1f8dda2	2026-05-07 13:39:08	2027-05-07 13:39:08	2	f	/var/lib/adpki/issued/Request-2f5027bfa1f8dda2/certificate.crt	/var/lib/adpki/issued/Request-2f5027bfa1f8dda2/private.key	/var/lib/adpki/issued/Request-2f5027bfa1f8dda2/fullchain.pem	\N	2026-05-07 11:39:08	2026-05-07 11:39:08	f	\N	\N	ecdsa	\N	P384	issued	\N	1	2026-05-07 11:39:08	\N	\N	\N	\N
28	tls	2Request	["2Request.de"]	3c505bb5b4624d1d	2026-05-07 13:40:42	2027-05-07 13:40:42	2	f	/var/lib/adpki/issued/2Request-3c505bb5b4624d1d/certificate.crt	/var/lib/adpki/issued/2Request-3c505bb5b4624d1d/private.key	/var/lib/adpki/issued/2Request-3c505bb5b4624d1d/fullchain.pem	\N	2026-05-07 11:39:35	2026-05-07 11:40:42	f	\N	\N	ecdsa	\N	P256	issued	11	1	2026-05-07 11:40:42	\N	\N	\N	{"ou": "Teste", "type": "tls", "curve": "P256", "email": "ali@ali.de", "state": "thu", "country": "DE", "san_dns": ["2Request.de"], "san_ips": [], "key_size": null, "key_type": "ecdsa", "locality": "te", "parent_id": 2, "common_name": "2Request", "organization": "te"}
29	codesign	codeanfrage	\N	\N	\N	\N	2	f	\N	\N	\N	\N	2026-05-07 11:47:16	2026-05-07 11:47:16	f	\N	\N	rsa	2048	\N	pending	11	\N	\N	\N	\N	\N	{"ou": "Teste", "type": "codesign", "curve": null, "email": "ali@ali.de", "state": "thu", "country": "DE", "san_dns": [], "san_ips": [], "key_size": 2048, "key_type": "rsa", "locality": "te", "parent_id": 2, "common_name": "codeanfrage", "organization": "te"}
30	client	Max Mustermann	\N	\N	\N	\N	2	f	\N	\N	\N	\N	2026-05-07 11:58:11	2026-05-07 11:58:11	f	\N	\N	\N	\N	\N	pending	11	\N	\N	\N	\N	\N	{"csr": "-----BEGIN CERTIFICATE REQUEST-----\\nMIICiTCCAXECAQAwRDEXMBUGA1UEAwwOTWF4IE11c3Rlcm1hbm4xDzANBgNVBAoM\\nBkFELU1JVDELMAkGA1UECwwCSVQxCzAJBgNVBAYTAkRFMIIBIjANBgkqhkiG9w0B\\nAQEFAAOCAQ8AMIIBCgKCAQEAvgi/bUw/7eJwABGuDph4wTcGsFOJ/tlSBwvXuhwH\\ntVG1B1+vzj9NtZ0RYgO5ssIKYdutQ/VbcTzec9MqKaT2lk2tZDxf5fMaej9L/eqt\\nRPfrBXJdnW4dS286qgJFmyY7UgGAHRuTrBeF4AQSN0wKAw8Asj7ndBHY6gfuuPUn\\nryuIgc5taNtrNkq0UQeMVLJdyqfcqT8Pg39AibpxR73Z/fmtEaa3V6p/sYRXvJYe\\ntrOfpBrgZOm6B5XOUqpImVevtHxtv/fAh+rSOteHqBKa2eKIKNn2k98VdCjqrzv9\\nwR7C/b3FLgCGBiLJzRt971F160jDwNulfahcXGvWpsyN6QIDAQABoAAwDQYJKoZI\\nhvcNAQELBQADggEBAGfRKkVNRHolRLu2ZjgQ/dXfY6iJlrLgMto67ClLyUtXePjE\\nCDVlHNDDGq3/glVtKkbrAl4rq8tX2jeOsJQ/WjzAqjBoK6MCNvuTfdxTj7zYQIHP\\nJiC3hrXT23k32deGPmX2nxQy44OpdmsgJSctPaTW5YZP0ex5AUMHa9HDLZCYlnxi\\nAkZZ4/ijYaSAIVMCMcXYheuo9Ee44JikLu+C5USzNOMsDCNH+YOdzgDSv0u/VqQA\\nZL0Zfe+XtWLlxPaNu9RXWCnH8nVw33dctRMa3SFF/CFRYQLlyQDRQZzt/2EdBB3O\\n3xtXxVkjD2rHXmssfLz4r1z3WMN+3z05ZwsfNa8=\\n-----END CERTIFICATE REQUEST-----", "type": "client", "method": "csr", "parent_id": "2", "common_name": "Max Mustermann"}
31	tls	test.local	\N	\N	\N	\N	2	f	\N	\N	\N	\N	2026-05-07 11:58:22	2026-05-07 11:58:22	f	\N	\N	\N	\N	\N	pending	11	\N	\N	\N	\N	\N	{"csr": "-----BEGIN CERTIFICATE REQUEST-----\\nMIICWjCCAUICAQAwFTETMBEGA1UEAwwKdGVzdC5sb2NhbDCCASIwDQYJKoZIhvcN\\nAQEBBQADggEPADCCAQoCggEBALcjWfw/TypFoYGjhjuKWsu9UGT5NaLxRVzw/D8C\\nKqGzU7WTMz/PQH6Rzp0jVG7yZGbkCpy0fM9IXivmy7GlLQiMHOYJw8RFMWz+QewJ\\nN/gU1KB4QhPTLlyOch6R/aeDhUMR7/tLWF92MGZwAWXLCPGlSL/SyVL4iBleHqKt\\nD0O6EQRtGZ+VTuR2NhcbovAdZc//cq6aFBhY+pHT1704NmSNq3NwgPr3DIS6tXfm\\nXFi2+JA/s+hRy8LG9yXq3n7R0xKdrtRldyrmN9PYrkOvN7SJWWZsBRvz/RN77pAr\\nNaUbI9CB2if1ot6+M71ZBVfLtMm1DFxHH/WXTUwecsY/ZhMCAwEAAaAAMA0GCSqG\\nSIb3DQEBCwUAA4IBAQBGIJJqJeLnTciDvQBd2O1aYNDoxgCGlKgtvC8omroaMwNS\\nqtpD2DEVAsoM1JSDUqz6HbYng8JOWXgaIeuRBtJWM7SEKV7TQkz9OW+YpokyAxOT\\nrNBH8WPE6LhVeZe3Spg7/t/BnVJgQDnai2gwxADOtUNXYYWIu/3bbbDDmwDPZWH+\\nNUnzXvi89c7Kmr+wxNeToJlVRbe9yiJiARiUEBxtJ9z3VqQVeF7oT5LmUfCc9fo0\\nc0+WSfjP69C+slJ9LA8eQw81tQOEEWXYdoQDYqQsSUnXhosCwAbThgB0PrWtDtLW\\ngKW0k5eE7xZrpnPG3/i+6MUT4nT/I/lRb/98Cy00\\n-----END CERTIFICATE REQUEST-----", "type": "tls", "method": "csr", "parent_id": "2", "common_name": "test.local"}
DATA);


        DB::statement("SELECT setval('settings_id_seq', COALESCE((SELECT MAX(id) FROM settings), 1));");
        DB::statement("SELECT setval('users_id_seq', COALESCE((SELECT MAX(id) FROM users), 1));");
        DB::statement("SELECT setval('certificates_id_seq', COALESCE((SELECT MAX(id) FROM certificates), 1));");


    }

    private function copy(string $table, array $columns, string $data): void
    {
        foreach (explode("\n", trim($data)) as $line) {
            if (trim($line) === '') {
                continue;
            }

            $values = explode("\t", $line);

            $row = [];
            foreach ($columns as $index => $column) {
                $value = $values[$index] ?? null;

                $row[$column] = match ($value) {
                    '\N' => null,
                    't' => true,
                    'f' => false,
                    default => $value,
                };
            }

            DB::table($table)->insert($row);
        }
    }
}