import http from 'k6/http';
import { sleep } from 'k6';

export const options = {
    vus: 10, // rate limit
    duration: '240s',
};

export default function () {
    
    const res = http.post(
        'https://relay.<instance>/api/console/sends',
        {
            from: 'supun@domain.com',
            to: 'accept@simulator.<instance>',
            subject: 'My subject',
            body_text: 'This is the text',
        },
        {
            headers: {
                'Authorization': 'Bearer',
            }
        }
    );

    sleep(1);

}
