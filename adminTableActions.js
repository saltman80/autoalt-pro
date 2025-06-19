const settings = window.autoAltPro || {};
const restUrl = settings.restUrl;
const restNonce = settings.restNonce;
if ( ! restUrl || ! restNonce ) {
    return;
}
document.addEventListener('DOMContentLoaded', function(){
    const buttons = document.querySelectorAll('button[data-endpoint]');
    buttons.forEach(button => {
        button.addEventListener('click', async function(event){
            event.preventDefault();
            if ( button.disabled ) {
                return;
            }
            const originalText = button.textContent;
            button.disabled = true;
            button.textContent = 'Processing?';
            const endpoint = button.getAttribute('data-endpoint');
            const url = `${restUrl.replace(/\/$/, '')}/${endpoint.replace(/^\/|\/$/g, '')}`;
            // Remove existing notices
            document.querySelectorAll('.auto-alt-notice').forEach(n => n.remove());
            try {
                const response = await fetch(url, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-WP-Nonce': restNonce
                    },
                    body: JSON.stringify({})
                });
                if ( ! response.ok ) {
                    throw new Error(`Request failed with status ${response.status}`);
                }
                const result = await response.json();
                if ( result.data && Array.isArray(result.data) ) {
                    result.data.forEach(item => {
                        const { id, alt, caption } = item;
                        const row = document.querySelector(`[data-attachment-id="${id}"]`);
                        if ( row ) {
                            if ( typeof alt === 'string' ) {
                                const altCell = row.querySelector('.alt-text');
                                if ( altCell ) {
                                    altCell.textContent = alt;
                                }
                            }
                            if ( typeof caption === 'string' ) {
                                const captionCell = row.querySelector('.caption');
                                if ( captionCell ) {
                                    captionCell.textContent = caption;
                                }
                            }
                        }
                    });
                }
                if ( result.message ) {
                    const notice = document.createElement('div');
                    notice.className = 'notice notice-success auto-alt-notice';
                    notice.textContent = result.message;
                    const table = button.closest('table');
                    if ( table && table.parentNode ) {
                        table.parentNode.insertBefore(notice, table);
                    } else {
                        document.body.insertBefore(notice, document.body.firstChild);
                    }
                    setTimeout(() => notice.remove(), 5000);
                }
            } catch (error) {
                console.error(error);
                const notice = document.createElement('div');
                notice.className = 'notice notice-error auto-alt-notice';
                notice.textContent = 'An error occurred. Please try again.';
                const table = button.closest('table');
                if ( table && table.parentNode ) {
                    table.parentNode.insertBefore(notice, table);
                } else {
                    document.body.insertBefore(notice, document.body.firstChild);
                }
                setTimeout(() => notice.remove(), 10000);
            } finally {
                button.disabled = false;
                button.textContent = originalText;
            }
        });
    });
});