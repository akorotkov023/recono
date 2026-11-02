import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    connect() {
        let show = document.getElementById('app_registry_index');
        let email = document.getElementById('app_registry_command_restart_email');
        let create = document.getElementById('app_registry_command_restart_create');
        let extract = document.getElementById('app_registry_command_restart_extract');
        let check = document.getElementById('app_registry_command_restart_check');
        let fix = document.getElementById('app_registry_command_restart_fix');
        let loader = document.getElementById('insert-loader');

        if(show) {
            show.addEventListener("click", (e) => {
                let el3 = document.getElementById('load-data');
                el3.innerHTML = '';

                let provider = document.getElementById('registry_command_main_provider_name').value;
                let start = document.getElementById('registry_command_main_start').value;
                let end = document.getElementById('registry_command_main_end').value;

                e.preventDefault()
                loader.classList.add('loader')
                this.getData(loader,provider,start,end).then()
            }, false)
        }

        window.addEventListener('load', () => {
            show.click();
        });


        if(email) {
            email.addEventListener("click", (e) => {
                let el3 = document.getElementById('load-data');
                el3.innerHTML = '';

                let provider = document.getElementById('registry_command_main_provider_name').value;
                let create_data = document.getElementById('registry_command_main_create_data').value;

                e.preventDefault()
                loader.classList.add('loader')
                this.postEmailCommand(loader,provider,create_data).then()
            }, false)
        }

        if(create) {
            create.addEventListener("click", (e) => {
                let el3 = document.getElementById('load-data');
                el3.innerHTML = '';

                let create_data = document.getElementById('registry_command_main_create_data').value;

                e.preventDefault()
                loader.classList.add('loader')
                this.postCreateCommand(loader,create_data).then()
            }, false)
        }

        if(extract) {
            extract.addEventListener("click", (e) => {
                let el3 = document.getElementById('load-data');
                el3.innerHTML = '';

                let provider = document.getElementById('registry_command_main_provider_name').value;

                e.preventDefault()
                loader.classList.add('loader')
                this.postExtractCommand(loader,provider).then()
            }, false)
        }

        if(check) {
            check.addEventListener("click", (e) => {
                let el3 = document.getElementById('load-data');
                el3.innerHTML = '';

                let provider = document.getElementById('registry_command_main_provider_name').value;
                let create_data = document.getElementById('registry_command_main_create_data').value;

                e.preventDefault()
                loader.classList.add('loader')
                this.postCheckCommand(loader,provider,create_data).then()
            }, false)
        }

        if(fix) {
            fix.addEventListener("click", (e) => {
                let el3 = document.getElementById('load-data');
                el3.innerHTML = '';

                let provider = document.getElementById('registry_command_main_provider_name').value;

                e.preventDefault()
                loader.classList.add('loader')
                this.postFixCommand(loader,provider).then()
            }, false)
        }
    }

    async getData(loader,provider,start,end) {

        const response = await fetch('/registry_index/' + provider + '/' + start + '/' + end);

        this.element.innerHTML = await response.text();
        loader.classList.remove('loader')
    }

    async postEmailCommand(loader,provider,create_data) {
        let response = await fetch('/registry/command/restart/' + provider + '/' + create_data + '/email');
        let messagesValue = await response.json();
        let message = messagesValue.join('\n').replace(/\n/g, '<br>');
        this.showBootstrapAlert(message);
        loader.classList.remove('loader')
    }

    async postCreateCommand(loader,create_data) {
        let response = await fetch('/registry/command/restart/' + create_data + '/create');
        let messagesValue = await response.json();
        let message = messagesValue.join('\n')
        this.showBootstrapAlert(message);
        loader.classList.remove('loader')
    }

    async postExtractCommand(loader,provider) {
        let response = await fetch('/registry/command/restart/' + provider + '/extract');
        let messagesValue = await response.json();
        let message = messagesValue.join('\n')
        this.showBootstrapAlert(message);
        loader.classList.remove('loader')
    }

    async postCheckCommand(loader,provider,create_data) {
        let response = await fetch('/registry/command/restart/' + provider + '/' + create_data + '/check');
        let messagesValue = await response.json();
        let message = messagesValue.join('\n')
        this.showBootstrapAlert(message);
        loader.classList.remove('loader')
    }

    async postFixCommand(loader,provider) {
        let response = await fetch('/registry/command/restart/' + provider + '/fix');
        let messagesValue = await response.json();
        let message = messagesValue.join('\n')
        this.showBootstrapAlert(message);
        loader.classList.remove('loader')
    }

    showBootstrapAlert(message) {
        const alert = document.createElement('div');
        alert.className = 'alert alert-info alert-dismissible fade show position-fixed top-0 start-50 translate-middle-x mt-3';
        alert.setAttribute('role', 'alert');
        alert.innerHTML = `
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        `;

        document.body.appendChild(alert);

        // Автоматически скрыть через 5 секунд
        // setTimeout(() => {
        //     alert.classList.remove('show');
        //     alert.addEventListener('transitionend', () => alert.remove());
        // }, 5000);
    }
}
