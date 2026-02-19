<h1>TRex Web Controller</h1>

<p>
Interface web para controle e edição de perfis do <strong>Cisco TRex Traffic Generator</strong>,
desenvolvida em PHP e executada com Nginx + PHP-FPM.
</p>

<hr>

<h2>📦 Requisitos</h2>

<h3>Sistema Operacional</h3>
<ul>
<li>CentOS 7.9</li>
</ul>

<h3>Dependências</h3>

<h4>Web Server</h4>
<ul>
<li>Nginx</li>
</ul>

<h4>PHP</h4>
<ul>
<li>PHP 8.3+</li>
<li>PHP-FPM</li>
</ul>

<h4>Extensões PHP necessárias</h4>
<pre>
php-cli
php-fpm
php-common
php-opcache
php-mbstring
php-xml
php-json
php-curl
</pre>

<h4>TRex</h4>
<p>Instalado em:</p>
<pre>/opt/trex/v3.06</pre>

<h4>ttyd (opcional)</h4>
<ul>
<li>Porta padrão: 7681</li>
</ul>

<hr>

<h2>🚀 Instalação</h2>

<h3>1️⃣ Instalar Nginx</h3>
<pre>
yum install nginx -y
systemctl enable nginx
</pre>

<h3>2️⃣ Instalar PHP 8.3 (Remi Repository)</h3>

<pre>
yum install epel-release yum-utils -y
yum-config-manager --enable remi-php83
</pre>

<pre>
yum install php php-fpm php-cli php-common php-opcache php-mbstring php-xml php-json php-curl -y
</pre>

<p>Verificar versão:</p>

<pre>php -v</pre>

<hr>

<h3>3️⃣ Configurar PHP-FPM</h3>

<p>Arquivo:</p>
<pre>/etc/php-fpm.d/www.conf</pre>

<p>Configuração mínima:</p>

<pre>
user = apache
group = apache
listen = 127.0.0.1:9000
</pre>

<p>Iniciar serviço:</p>

<pre>
systemctl enable php-fpm
systemctl start php-fpm
</pre>

<hr>

<h3>4️⃣ Configurar Nginx</h3>

<p>Criar arquivo:</p>

<pre>/etc/nginx/conf.d/trex.conf</pre>

<pre>
server {
    listen 80;
    server_name _;

    root /var/www/html;
    index trex-avancado.php index.php index.html;

    location / {
        try_files $uri $uri/ /trex-avancado.php;
    }

    location ~ \.php$ {
        include fastcgi_params;
        fastcgi_pass 127.0.0.1:9000;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    }
}
</pre>

<p>Testar configuração:</p>

<pre>
nginx -t
systemctl restart nginx
</pre>

<hr>

<h3>5️⃣ Deploy da Aplicação</h3>

<p>Copiar arquivos para:</p>

<pre>/var/www/html/</pre>

<p>Ajustar permissões:</p>

<pre>
chown -R root:root /var/www/html
chmod -R 755 /var/www/html
</pre>

<hr>

<h2>🔐 Configuração de Sudo</h2>

<p>Criar arquivo:</p>

<pre>/etc/sudoers.d/trex</pre>

<pre>
Defaults:apache !requiretty
Defaults:apache secure_path="/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin"

apache ALL=(root) NOPASSWD: /opt/trex/v3.06/trex-console
apache ALL=(root) NOPASSWD: /usr/local/bin/start-trex-nohup.sh
apache ALL=(root) NOPASSWD: /usr/local/bin/start-trex-nohup_bkp.sh
apache ALL=(root) NOPASSWD: /usr/local/bin/start-trex-nohup_bkp2.sh
</pre>

<p>Validar:</p>

<pre>visudo -c</pre>

<hr>

<h2>📂 Estrutura Esperada</h2>

<pre>
/var/www/html/
 ├── trex-avancado.php
 ├── style.css
 ├── imagens
 └── .git
</pre>

<hr>

<h2>⚙️ Configuração do TRex</h2>

<p>Diretório base:</p>

<pre>/opt/trex/v3.06</pre>

<p>Perfis organizados em:</p>

<pre>
cap2/
stl/
astf/
avl/
</pre>

<hr>

<h2>🖥️ Console Web (Opcional)</h2>

<pre>
ttyd -p 7681 --writable /opt/trex/v3.06/trex-ttyd.sh
</pre>

<p>Acesso:</p>

<pre>http://IP_DO_SERVIDOR:7681</pre>

<hr>

<h2>🔍 Verificação Final</h2>

<pre>
ss -lntp | egrep ':80|:9000|:7681'
</pre>

<pre>
curl -I http://127.0.0.1
</pre>

<hr>

<h2>🛡️ Segurança</h2>

<ul>
<li>Não expor ttyd externamente</li>
<li>Usar firewall restritivo</li>
<li>Desabilitar login root via SSH</li>
<li>Implementar HTTPS se exposto à internet</li>
<li>Criar wrapper seguro para execução do TRex</li>
</ul>

<hr>

<h2>📌 Arquitetura</h2>

<pre>
Cliente
   ↓
Nginx (:80)
   ↓
PHP-FPM (:9000 localhost)
   ↓
Sudo (controlado)
   ↓
TRex
</pre>

<hr>

<h2>🧪 Status</h2>

<ul>
<li>Testado em CentOS 7.9</li>
<li>PHP 8.3</li>
<li>Nginx</li>
<li>Integração com TRex funcional</li>
</ul>
