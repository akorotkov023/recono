import { Controller } from '@hotwired/stimulus';

export default class extends Controller {

    connect() {
        let showMoreElement = document.getElementById('act_search_show_more');
        let additionalInput = document.getElementsByClassName('additional-input');
        additionalInput[0].style.display = 'none';

        showMoreElement.addEventListener('click', function() {
            let additionalInput = document.getElementsByClassName('additional-input');
            if (additionalInput[0].style.display === 'none') {
                additionalInput[0].style.display = 'block';
            } else {
                additionalInput[0].style.display = 'none';
            }
        });


        let el = document.getElementById('button-to-loader');
        let el2 = document.getElementById('insert-loader');
        if(el) {
            el.addEventListener("click", (e) => {
                let el3 = document.getElementById('load-data');
                el3.innerHTML = '';
                let checkbox = document.getElementsByClassName('form-check-input');
                let values = [];
                values.push(0);
                for (let i = 0; i < checkbox.length; i++) {
                    if (checkbox[i].checked) {
                        values.push(checkbox[i].value);
                    }
                }
                let ids = values.join(', ');
                let id = document.getElementById('act_search_partner_id').selectedOptions[0].value;
                let name = document.getElementById('act_search_partner_id').selectedOptions[0].text;
                let month = document.getElementById('act_search_month').value;
                let type = document.getElementById('act_search_type').selectedOptions[0].value;
                e.preventDefault()
                el2.classList.add('loader')

                this.getData(el2,month,type,name,id,ids).then()
            }, false)
        }
    }

    async getData(el2,month,type,name,id,ids) {
        const response = await fetch('/act/' + month + '/' + type + '/' + name +  '/' + id +  '/' + ids + '/generate' );
        this.element.innerHTML = await response.text();
        el2.classList.remove('loader')
    }
}
