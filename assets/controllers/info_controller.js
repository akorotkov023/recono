import { Controller } from '@hotwired/stimulus';

export default class extends Controller {

    connect() {
        let el = document.getElementById('button-to-loader');
        let el2 = document.getElementById('insert-loader');
        if(el) {
            el.addEventListener("click", (e) => {
                let el3 = document.getElementById('load-data');
                el3.innerHTML = '';

                let dataInput = document.getElementById('filter_start').value;
                e.preventDefault()
                el2.classList.add('loader')
                this.getData(el2,dataInput).then()
            }, false)
        }

        window.addEventListener('load', () => {
            el.click();
        });
    }

    async getData(el2, value) {
        const response = await fetch('/info/' + value );
        this.element.innerHTML = await response.text();
        el2.classList.remove('loader')
    }
}
