/*
 * Welcome to your app's main JavaScript file!
 *
 * We recommend including the built version of this JavaScript file
 * (and its CSS file) in your base layout (base.html.twig).
 */

// any CSS you import will output into a single css file (app.scss in this case)
import './styles/app.scss';

// start the Stimulus application
import './bootstrap';
const $ = require('jquery');
const _bootstrap = require('bootstrap');
require('bootstrap-datepicker');

!function(a){a.fn.datepicker.dates.ru={days:["Воскресенье","Понедельник","Вторник","Среда","Четверг","Пятница","Суббота"],daysShort:["Вск","Пнд","Втр","Срд","Чтв","Птн","Суб"],daysMin:["Вс","Пн","Вт","Ср","Чт","Пт","Сб"],months:["Январь","Февраль","Март","Апрель","Май","Июнь","Июль","Август","Сентябрь","Октябрь","Ноябрь","Декабрь"],monthsShort:["Янв","Фев","Мар","Апр","Май","Июн","Июл","Авг","Сен","Окт","Ноя","Дек"],today:"Сегодня",clear:"Очистить",format:"dd.mm.yyyy",weekStart:1,monthsTitle:"Месяцы"}}(jQuery)

$( () => {
    $('.datepicker').datepicker({
        format: 'yyyy-mm-dd',
        language: 'EN',
        endDate: '1d',
        autoclose: true
    });
});

$( () => {
    $('.datepicker-month').datepicker( {
        format: "mm-yyyy",
        viewMode: "months",
        minViewMode: "months",
        endDate: '-1m'
    });
});

$( () => {
    let el = document.getElementById('button-to-clipboard');
    if(el){
        el.addEventListener("click", ()=> {
            let name = document.getElementsByClassName('transaction_diff_reg');
            if (name.length > 0) {
                let res = name[0]['defaultValue']
                    .split(',')
                    .map(s => s.replace(/'/g, '').trim())
                    .join('\n');
                navigator.clipboard.writeText(res).then();
                el.innerHTML = "Copied!";
            } else navigator.clipboard.writeText('0').then()
        }, false)
    }

    let elf = document.getElementById('btn-to-copy');
    if(elf){
        elf.addEventListener("click", ()=> {
            let name = document.getElementsByClassName('transaction_diff_base');
            if (name.length > 0) {
                let result = name[0]['defaultValue']
                    .split(',')
                    .map(s => s.replace(/'/g, '').trim())
                    .join('\n');
                navigator.clipboard.writeText(result).then();
                elf.innerHTML = "Copied!";
            } else navigator.clipboard.writeText('0').then()
        }, false)
    }
});
