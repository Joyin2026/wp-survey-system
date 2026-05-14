/**
 * WP Survey System - 前端问卷脚本
 *
 * @package WP_Survey
 * @version 1.0.0
 */

(function($) {
    'use strict';

    /**
     * 问卷前端控制器
     */
    var Survey = {
        surveyId: null,
        responseId: null,
        questions: [],
        currentStep: 0,
        displayMode: 'step',
        settings: {},
        answeredQuestions: {},

        /**
         * 初始化问卷
         */
        init: function(config) {
            var self = this;

            // 调试：检查 wpsurvey 对象
            if (typeof wpsurvey === 'undefined') {
                console.error('WPSurvey: wpsurvey object is not defined. Please check wp_localize_script.');
            }

            self.surveyId = config.survey_id;
            self.responseId = config.response_id;
            self.questions = config.questions || [];
            self.displayMode = config.display_mode || 'step';
            self.settings = config.settings || {};

            // 应用样式设置
            self.applySettings();

            // 绑定事件
            self.bindEvents();

            // 如果是全部模式，直接渲染
            if (self.displayMode !== 'step') {
                self.renderSurvey();
            }
        },

        /**
         * 应用问卷样式设置
         */
        applySettings: function() {
            var $style = $('#wpsurvey-dynamic-style');
            if (!$style.length) {
                $style = $('<style id="wpsurvey-dynamic-style"></style>').appendTo('head');
            }

            var css = '';
            if (this.settings.primary_color) {
                css += '.wpsurvey-container .wpsurvey-progress-fill { background: ' + this.settings.primary_color + '; }';
                css += '.wpsurvey-container .wpsurvey-question-number { color: ' + this.settings.primary_color + '; }';
            }
            if (this.settings.button_color) {
                css += '.wpsurvey-container .wpsurvey-btn-primary { background: ' + this.settings.button_color + '; border-color: ' + this.settings.button_color + '; }';
            }
            if (this.settings.accent_color) {
                css += '.wpsurvey-container .wpsurvey-progress { background: ' + this.settings.accent_color + '; }';
            }
            if (this.settings.custom_css) {
                css += this.settings.custom_css;
            }

            $style.text(css);
        },

        /**
         * 绑定前端事件
         */
        bindEvents: function() {
            var self = this;

            // 开始按钮
            $(document).on('click', '.wpsurvey-btn-start', function() {
                self.startSurvey();
            });

            // 上一题
            $(document).on('click', '.wpsurvey-btn-prev', function() {
                self.prevStep();
            });

            // 下一题
            $(document).on('click', '.wpsurvey-btn-next', function() {
                self.nextStep();
            });

            // 提交问卷
            $(document).on('click', '.wpsurvey-btn-submit', function() {
                self.submitSurvey();
            });

            // 选择题（单选/多选/下拉）
            $(document).on('change', '.wpsurvey-radio input[type="radio"], .wpsurvey-checkbox input[type="checkbox"], .wpsurvey-select select', function() {
                var questionId = $(this).closest('.wpsurvey-question').data('question-id');
                self.saveAnswer(questionId);
                self.updateNextButtonState();
            });

            // 文本输入
            $(document).on('input', '.wpsurvey-input, .wpsurvey-textarea', function() {
                var questionId = $(this).closest('.wpsurvey-question').data('question-id');
                self.answeredQuestions[questionId] = $(this).val();
                self.updateNextButtonState();
            });

            // 评分题
            $(document).on('click', '.wpsurvey-rating-item', function() {
                var $item = $(this);
                var value = $item.data('value');
                var questionId = $item.closest('.wpsurvey-question').data('question-id');

                $item.siblings().removeClass('selected');
                $item.addClass('selected');

                // 更新隐藏输入
                $item.closest('.wpsurvey-question').find('.wpsurvey-rating-input').val(value);
                self.answeredQuestions[questionId] = value;
                self.updateNextButtonState();
            });

            // 矩阵题
            $(document).on('change', '.wpsurvey-matrix input[type="radio"]', function() {
                var $matrix = $(this).closest('.wpsurvey-matrix');
                var questionId = $matrix.closest('.wpsurvey-question').data('question-id');
                var answers = {};

                $matrix.find('tbody tr').each(function(rowIdx) {
                    var $row = $(this);
                    var selected = $row.find('input[type="radio"]:checked').val();
                    if (selected !== undefined) {
                        answers['row_' + rowIdx] = parseInt(selected);
                    }
                });

                self.answeredQuestions[questionId] = answers;
                self.updateNextButtonState();
            });
        },

        /**
         * 开始问卷
         */
        startSurvey: function() {
            var self = this;

            if (typeof wpsurvey === 'undefined' || !wpsurvey.ajax_url) {
                alert('问卷初始化失败，请刷新页面重试');
                console.error('WPSurvey: wpsurvey object is not defined');
                return;
            }

            $.ajax({
                url: wpsurvey.ajax_url,
                type: 'POST',
                data: {
                    action: 'wpsurvey_start',
                    survey_id: self.surveyId,
                    nonce: wpsurvey.nonce
                },
                beforeSend: function() {
                    $('.wpsurvey-btn-start').prop('disabled', true).text('加载中...');
                },
                success: function(response) {
                    if (response.success) {
                        self.responseId = response.data.response_id;
                        self.questions = response.data.questions;
                        self.displayMode = response.data.display_mode;
                        self.settings = response.data.settings;

                        // 重新渲染问卷
                        self.renderSurvey();
                    } else {
                        alert(response.data.message || wpsurvey.i18n.submit_error);
                        $('.wpsurvey-btn-start').prop('disabled', false).text('开始');
                    }
                },
                error: function() {
                    alert(wpsurvey.i18n.submit_error);
                    $('.wpsurvey-btn-start').prop('disabled', false).text('开始');
                }
            });
        },

        /**
         * 渲染问卷
         */
        renderSurvey: function() {
            var self = this;
            var $container = $('.wpsurvey-container');

            // 应用设置
            self.applySettings();

            // 构建题目HTML
            var questionsHtml = '';

            $.each(self.questions, function(index, question) {
                questionsHtml += self.renderQuestion(question, index);
            });

            // 替换容器内容
            $container.find('.wpsurvey-questions').html(questionsHtml);

            // 更新进度条
            if (self.displayMode === 'step') {
                $container.addClass('wpsurvey-step-mode');
                $container.find('.wpsurvey-progress').show();
                $container.find('.wpsurvey-buttons').show();
                self.showStep(0);
            } else {
                $container.removeClass('wpsurvey-step-mode');
                $container.find('.wpsurvey-progress').hide();
                $container.find('.wpsurvey-buttons').show();
                self.showStep(0);
                self.updateNextButtonState();
            }
        },

        /**
         * 渲染单个题目
         */
        renderQuestion: function(question, index) {
            var html = '<div class="wpsurvey-question" data-question-id="' + question.id + '" data-index="' + index + '">';
            html += '<div class="wpsurvey-question-header">';
            html += '<span class="wpsurvey-question-number">第 ' + (index + 1) + ' 题</span>';
            if (question.required) {
                html += '<span class="wpsurvey-required">*</span>';
            }
            html += '</div>';
            html += '<div class="wpsurvey-question-text">' + this.escapeHtml(question.question_text) + '</div>';
            html += '<div class="wpsurvey-question-body">';

            switch (question.question_type) {
                case 'radio':
                    html += this.renderRadio(question, index);
                    break;
                case 'checkbox':
                    html += this.renderCheckbox(question, index);
                    break;
                case 'select':
                    html += this.renderSelect(question, index);
                    break;
                case 'text':
                    html += '<input type="text" class="wpsurvey-input" data-question-id="' + question.id + '" placeholder="请输入...">';
                    break;
                case 'textarea':
                    html += '<textarea class="wpsurvey-textarea" data-question-id="' + question.id + '" rows="4" placeholder="请输入..."></textarea>';
                    break;
                case 'rating':
                    html += this.renderRating(question, index);
                    break;
                case 'matrix':
                    html += this.renderMatrix(question, index);
                    break;
            }

            html += '</div></div>';
            return html;
        },

        /**
         * 渲染单选题
         */
        renderRadio: function(question, index) {
            var html = '<div class="wpsurvey-radio">';
            var options = question.options || [];
            $.each(options, function(i, option) {
                html += '<label class="wpsurvey-option">';
                html += '<input type="radio" name="question_' + question.id + '" value="' + i + '" data-option-index="' + i + '">';
                html += '<span>' + this.escapeHtml(option.option_text) + '</span>';
                html += '</label>';
            }.bind(this));
            html += '</div>';
            return html;
        },

        /**
         * 渲染多选题
         */
        renderCheckbox: function(question, index) {
            var html = '<div class="wpsurvey-checkbox">';
            var options = question.options || [];
            $.each(options, function(i, option) {
                html += '<label class="wpsurvey-option">';
                html += '<input type="checkbox" name="question_' + question.id + '[]" value="' + i + '" data-option-index="' + i + '">';
                html += '<span>' + this.escapeHtml(option.option_text) + '</span>';
                html += '</label>';
            }.bind(this));
            html += '</div>';
            return html;
        },

        /**
         * 渲染下拉选择
         */
        renderSelect: function(question, index) {
            var html = '<div class="wpsurvey-select"><select name="question_' + question.id + '">';
            html += '<option value="">请选择...</option>';
            var options = question.options || [];
            $.each(options, function(i, option) {
                html += '<option value="' + i + '">' + this.escapeHtml(option.option_text) + '</option>';
            }.bind(this));
            html += '</select></div>';
            return html;
        },

        /**
         * 渲染评分题
         */
        renderRating: function(question, index) {
            var maxScore = question.settings && question.settings.max_score ? question.settings.max_score : 5;
            var html = '<div class="wpsurvey-rating">';
            for (var i = 1; i <= maxScore; i++) {
                html += '<span class="wpsurvey-rating-item" data-value="' + i + '">' + i + '</span>';
            }
            html += '<input type="hidden" class="wpsurvey-rating-input" name="question_' + question.id + '" value="">';
            html += '</div>';
            return html;
        },

        /**
         * 渲染矩阵题
         */
        renderMatrix: function(question, index) {
            var rows = question.settings && question.settings.rows ? question.settings.rows : [];
            var columns = question.settings && question.settings.columns ? question.settings.columns : [];
            var html = '<div class="wpsurvey-matrix"><table><thead><tr><th></th>';
            $.each(columns, function(i, col) {
                html += '<th>' + this.escapeHtml(col) + '</th>';
            }.bind(this));
            html += '</tr></thead><tbody>';
            $.each(rows, function(rIdx, row) {
                html += '<tr><td>' + this.escapeHtml(row) + '</td>';
                $.each(columns, function(cIdx, col) {
                    html += '<td><input type="radio" name="matrix_' + question.id + '_' + rIdx + '" value="' + cIdx + '"></td>';
                });
                html += '</tr>';
            }.bind(this));
            html += '</tbody></table></div>';
            return html;
        },

        /**
         * HTML 转义
         */
        escapeHtml: function(text) {
            if (!text) return '';
            var div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        },

        /**
         * 显示指定步骤
         */
        showStep: function(step) {
            var self = this;
            var $container = $('.wpsurvey-container');
            var $questions = $container.find('.wpsurvey-question');

            $questions.hide();
            $questions.eq(step).show();

            // 更新进度
            var progress = ((step + 1) / $questions.length) * 100;
            $container.find('.wpsurvey-progress-fill').css('width', progress + '%');
            $container.find('.wpsurvey-progress-text').text((step + 1) + ' / ' + $questions.length);

            // 更新按钮状态
            self.updateNextButtonState();
            $('.wpsurvey-btn-prev').prop('disabled', step === 0);
        },

        /**
         * 上一题
         */
        prevStep: function() {
            if (this.currentStep > 0) {
                this.currentStep--;
                this.showStep(this.currentStep);
            }
        },

        /**
         * 下一题
         */
        nextStep: function() {
            var $container = $('.wpsurvey-container');
            var $questions = $container.find('.wpsurvey-question');
            var totalSteps = $questions.length;

            if (this.currentStep < totalSteps - 1) {
                // 检查当前题目是否已答（如果是必填）
                var $currentQuestion = $questions.eq(this.currentStep);
                var questionId = $currentQuestion.data('question-id');
                var question = this.questions[this.currentStep];

                if (question.required && !this.isQuestionAnswered(question)) {
                    alert(wpsurvey.i18n.required);
                    return;
                }

                this.currentStep++;
                this.showStep(this.currentStep);
            }
        },

        /**
         * 检查题目是否已作答
         */
        isQuestionAnswered: function(question) {
            var answered = this.answeredQuestions[question.id];
            if (answered !== undefined && answered !== '' && answered !== null) {
                // 对于矩阵题，需要检查是否所有行都已作答
                if (question.question_type === 'matrix') {
                    var rows = question.settings && question.settings.rows ? question.settings.rows.length : 0;
                    if (rows > 0 && answered) {
                        // 检查是否每一行都有答案
                        for (var i = 0; i < rows; i++) {
                            if (answered['row_' + i] === undefined) {
                                return false;
                            }
                        }
                    } else {
                        return false;
                    }
                }
                return true;
            }

            // 检查单选/多选
            var $q = $('.wpsurvey-question[data-question-id="' + question.id + '"]');
            if ($q.find('input[type="radio"]:checked, input[type="checkbox"]:checked').length > 0) {
                return true;
            }
            if ($q.find('select').val()) {
                return true;
            }

            // 矩阵题：检查所有行是否都已选中
            if (question.question_type === 'matrix') {
                var rows = question.settings && question.settings.rows ? question.settings.rows.length : 0;
                var answeredRows = 0;
                $q.find('.wpsurvey-matrix tbody tr').each(function(idx) {
                    if ($(this).find('input[type="radio"]:checked').length > 0) {
                        answeredRows++;
                    }
                });
                return answeredRows >= rows;
            }

            return false;
        },

        /**
         * 更新下一步按钮状态
         */
        updateNextButtonState: function() {
            var self = this;
            var $container = $('.wpsurvey-container');
            var $questions = $container.find('.wpsurvey-question');
            var $btnNext = $container.find('.wpsurvey-btn-next');
            var $btnSubmit = $container.find('.wpsurvey-btn-submit');

            if (this.displayMode === 'step') {
                var $currentQuestion = $questions.eq(this.currentStep);
                var question = this.questions[this.currentStep];

                if (question && question.required && !this.isQuestionAnswered(question)) {
                    $btnNext.prop('disabled', true);
                } else {
                    $btnNext.prop('disabled', false);
                }

                // 最后一道题时显示提交按钮，隐藏下一题按钮
                if (this.currentStep >= $questions.length - 1) {
                    $btnNext.hide();
                    $btnSubmit.show();
                } else {
                    $btnNext.show();
                    $btnSubmit.hide();
                }
            } else {
                // 全部模式：检查是否所有必填题都已作答
                var allAnswered = true;
                $.each(this.questions, function(i, q) {
                    if (q.required && !this.isQuestionAnswered(q)) {
                        allAnswered = false;
                        return false;
                    }
                }.bind(this));
                $btnSubmit.show();
                $btnSubmit.prop('disabled', !allAnswered);
                $btnNext.hide();
            }
        },

        /**
         * 保存答案（AJAX）
         */
        saveAnswer: function(questionId) {
            var self = this;
            var answerValue = null;
            var $q = $('.wpsurvey-question[data-question-id="' + questionId + '"]');

            var $radio = $q.find('input[type="radio"]:checked');
            if ($radio.length) {
                answerValue = $radio.val();
            }

            var $checkbox = $q.find('input[type="checkbox"]:checked');
            if ($checkbox.length) {
                answerValue = $checkbox.map(function() { return $(this).val(); }).get();
            }

            var $select = $q.find('select');
            if ($select.length) {
                answerValue = $select.val();
            }

            var $input = $q.find('.wpsurvey-input, .wpsurvey-textarea');
            if ($input.length) {
                answerValue = $input.val();
            }

            var $rating = $q.find('.wpsurvey-rating-input');
            if ($rating.length) {
                answerValue = $rating.val();
            }

            var $matrix = $q.find('.wpsurvey-matrix');
            if ($matrix.length) {
                var matrixAnswers = {};
                $matrix.find('tbody tr').each(function(rowIdx) {
                    var selected = $(this).find('input[type="radio"]:checked').val();
                    if (selected !== undefined) {
                        matrixAnswers['row_' + rowIdx] = parseInt(selected);
                    }
                });
                answerValue = matrixAnswers;
            }

            if (answerValue === null) return;

            self.answeredQuestions[questionId] = answerValue;

            $.ajax({
                url: wpsurvey.ajax_url,
                type: 'POST',
                data: {
                    action: 'wpsurvey_save_answer',
                    response_id: self.responseId,
                    question_id: questionId,
                    answer_value: answerValue,
                    nonce: wpsurvey.nonce
                },
                success: function(response) {
                    // 答案已保存
                }
            });
        },

        /**
         * 提交问卷
         */
        submitSurvey: function() {
            var self = this;

            // 检查所有必填题
            var unanswered = [];
            $.each(this.questions, function(i, q) {
                if (q.required && !this.isQuestionAnswered(q)) {
                    unanswered.push(i + 1);
                }
            }.bind(this));

            if (unanswered.length > 0) {
                alert('请完成所有必填题目（第 ' + unanswered.join(', ') + ' 题）');
                return;
            }

            $.ajax({
                url: wpsurvey.ajax_url,
                type: 'POST',
                data: {
                    action: 'wpsurvey_submit',
                    response_id: self.responseId,
                    nonce: wpsurvey.nonce
                },
                beforeSend: function() {
                    $('.wpsurvey-btn-submit').prop('disabled', true).text('提交中...');
                },
                success: function(response) {
                    if (response.success) {
                        var $container = $('.wpsurvey-container');
                        $container.find('.wpsurvey-card').html(
                            '<div class="wpsurvey-thankyou">' +
                            '<h2>提交成功</h2>' +
                            '<p>' + (response.data.message || '感谢您的参与！') + '</p>' +
                            '</div>'
                        );
                    } else {
                        alert(response.data.message || wpsurvey.i18n.submit_error);
                        $('.wpsurvey-btn-submit').prop('disabled', false).text('提交问卷');
                    }
                },
                error: function() {
                    alert(wpsurvey.i18n.submit_error);
                    $('.wpsurvey-btn-submit').prop('disabled', false).text('提交问卷');
                }
            });
        }
    };

    // 全局初始化函数（供外部门联问卷时调用）
    window.WPSurveyInit = function(config) {
        Survey.init(config);
    };

})(jQuery);
