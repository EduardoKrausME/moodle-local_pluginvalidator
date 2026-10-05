<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Pt_br Lang
 *
 * @package     local_pluginvalidator
 * @copyright   2026 Eduardo Kraus
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

$string['back'] = 'Voltar';
$string['engine'] = 'Motor de validação';
$string['engine_execution'] = 'Validação de execução';
$string['engine_execution_desc'] = 'Executa testes em tempo de execução dentro do Moodle: backup de atividades quando existe backup/moodle2, contratos das funções externas de db/services.php e instanciação do mod_form.php nos módulos de atividade.';
$string['enginebuiltin'] = 'Interno';
$string['engine_moodlepluginvalidate'] = 'Moodle Plugin Validate';
$string['engine_moodlepluginvalidate_desc'] = 'Validação estruturada do projeto EduardoKrausME/moodle-plugin-validate, com regras, checks, arquivos e linhas separados.';
$string['engineavailable'] = 'Motor de validação disponível';
$string['engineinstalled'] = 'Motor de validação {$a} instalado com sucesso.';
$string['engineinstallfailed'] = 'Não foi possível instalar o motor de validação: {$a}';
$string['enginemissing'] = 'O motor de validação ainda não está instalado.';
$string['enginenotinstalled'] = 'Instale o motor de validação antes de executar a validação.';
$string['enginesourcebundled'] = '(embutido)';
$string['enginesourcedownloaded'] = '(baixado)';
$string['executionbackuperror'] = 'A execução do backup falhou: {$a}';
$string['executionbackupnoinstance'] = 'A pasta backup/moodle2 existe, mas nenhuma instância instalada da atividade foi encontrada para executar um backup real.';
$string['executionbackupok'] = 'Backup executado com sucesso para o módulo de curso {$a}.';
$string['executionbackupunsupported'] = 'A pasta backup/moodle2 existe, mas a execução completa do backup atualmente é suportada apenas para módulos de atividade.';
$string['executionmodformclassinvalid'] = 'A classe de formulário {$a} não estende moodleform_mod.';
$string['executionmodformclassmissing'] = 'A classe de formulário esperada {$a} não foi encontrada.';
$string['executionmodformerror'] = 'O mod_form.php falhou durante a instanciação em tempo de execução: {$a}';
$string['executionmodformmissing'] = 'O plugin de atividade não contém mod_form.php.';
$string['executionmodformok'] = 'O mod_form.php foi carregado, instanciado e preenchido com sucesso.';
$string['executionnotapplicable'] = 'Nenhum teste de execução se aplica a este plugin.';
$string['executionservicecontractonly'] = 'A função externa {$a->name} possui contrato válido, mas a execução real foi ignorada: {$a->reason}';
$string['executionserviceexecuted'] = 'A função externa {$a} foi executada com sucesso e retornou um valor aceito pelo contrato de retorno declarado.';
$string['executionserviceskipparams'] = 'a função exige parâmetros reais de entrada';
$string['executionserviceskipwrite'] = 'a função não está explicitamente declarada como somente leitura';
$string['executionserviceerror'] = 'A função externa {$a->name} falhou na validação do contrato em tempo de execução: {$a->message}';
$string['executionserviceinvalid'] = 'A função externa {$a} possui uma definição inválida.';
$string['executionserviceok'] = 'A função externa {$a} foi carregada com sucesso e seus contratos de parâmetros e retorno são válidos.';
$string['executionservicesempty'] = 'O db/services.php existe, mas não define funções externas.';
$string['executionservicesloaderror'] = 'Não foi possível carregar db/services.php: {$a}';
$string['executionserviceunregistered'] = 'A função externa {$a} está declarada em db/services.php, mas não está registrada no Moodle.';
$string['executionservicewrongcomponent'] = 'A função externa {$a} está registrada para outro componente.';
$string['failed'] = 'Falhou';
$string['howtofix'] = 'Como corrigir';
$string['installengine'] = 'Instalar motor';
$string['invalidengine'] = 'O motor de validação selecionado não existe.';
$string['invalidplugin'] = 'O plugin selecionado não existe ou é um plugin nativo do Moodle.';
$string['nosearchresults'] = 'Nenhum plugin de terceiros foi encontrado para essa busca.';
$string['nothirdpartyplugins'] = 'Nenhum plugin de terceiros foi encontrado.';
$string['passed'] = 'Aprovado';
$string['pluginname'] = 'Validador de plugins';
$string['privacy:metadata'] = 'O Validador de plugins não armazena dados pessoais.';
$string['release'] = 'Release';
$string['result'] = 'Resultado';
$string['runvalidation'] = 'Executar validação';
$string['search'] = 'Buscar';
$string['searchplaceholder'] = 'Nome, componente ou tipo do plugin';
$string['searchplugins'] = 'Buscar plugins';
$string['searchresultsfor'] = 'Resultados para "{$a}"';
$string['statuserror'] = 'Erro';
$string['statusok'] = 'OK';
$string['statuswarning'] = 'Aviso';
$string['summaryerrors'] = 'Erros';
$string['summaryok'] = 'OK';
$string['summarywarnings'] = 'Avisos';
$string['thirdpartyonly'] = 'Somente plugins de extensão instalados são exibidos. Plugins nativos do Moodle são excluídos automaticamente.';
$string['updateengine'] = 'Atualizar motor';
$string['validationruntimeerror'] = 'Erro ao executar o validador';
$string['validations'] = 'Validações';
$string['validator_desc'] = 'Executa diretamente a biblioteca EduardoKrausME/moodle-plugin-validate contra este plugin instalado.';
$string['version'] = 'Versão';
$string['welcome'] = 'Validador de plugins';
$string['welcome_desc'] = 'Selecione um tipo de plugin para visualizar os plugins de terceiros instalados. Plugins nativos do Moodle são ocultados automaticamente.';
$string['whythishappened'] = 'Por que isso acontece';
