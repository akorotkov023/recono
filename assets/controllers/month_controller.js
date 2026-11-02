import { Controller } from '@hotwired/stimulus';

export default class extends Controller {

    connect() {

        let el = document.getElementById('button-to-loader');
        let el2 = document.getElementById('insert-loader');
        if(el) {
            el.addEventListener("click", (e) => {
                let el3 = document.getElementById('load-data');
                el3.innerHTML = '';

                let slug = document.getElementById('registry_search_partner_id').selectedOptions[0].text;
                let month = document.getElementById('registry_search_month').value;
                let format = document.getElementById('registry_search_type').selectedOptions[0].value;
                let status = document.getElementById('registry_search_status').selectedOptions[0].value;
                e.preventDefault()
                el2.classList.add('loader')

                this.getData(el2,month,format,slug,status).then()
            }, false)
        }
    }

    async getData(el2,month,format,slug,status) {
        const response = await fetch('/registry/' + month + '/' + format + '/' + slug + '/' + status + '/generate' );
        this.element.innerHTML = await response.text();
        el2.classList.remove('loader')
    }
}
