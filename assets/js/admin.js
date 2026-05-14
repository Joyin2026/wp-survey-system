/**
 * WP Survey System - 后台管理脚本
 *
 * @package WP_Survey
 * @version 1.0.0
 */

(function($) {
    'use strict';

    // 问卷构建器
    var SurveyBuilder = {
        questionIndex: 0,
        
        init: function() {
            this.initSortable();
            this.bindEvents();
            this.loadJumpTargets();
            this.initFloatingAddBtn();
            
        },
        
        /**
         * 初始化悬浮添加按钮
         */
        initFloatingAddBtn: function() {
            var self = this;
            var floatingBtn = jQuery("#wpsurvey-floating-add-btn");
            var builder = jQuery(".wpsurvey-questions-builder");

            if (!builder.length || !floatingBtn.length) return;

            function updateFloatingBtn() {
                var hasQuestions = builder.find(".wpsurvey-question-item").length >= 3;
                if (hasQuestions) {
                    floatingBtn.show();
                } else {
                    floatingBtn.hide();
                }
            }

            updateFloatingBtn();

            floatingBtn.off("click.wpsurvey").on("click.wpsurvey", function() {
                if (floatingBtn.hasClass("processing")) return;
                
                floatingBtn.addClass("processing");
                
                var type = jQuery("#wpsurvey-new-question-type").val() || "radio";
                self.addQuestion(type);
                
                setTimeout(function() {
                    floatingBtn.removeClass("processing");
                    updateFloatingBtn();
                }, 800);
            });
        },
        
        /**
         * 初始化拖拽排序
         */
        initSortable: function() {
            var $builder = $('.wpsurvey-questions-builder');
            
            if ($builder.length) {
                $builder.sortable({
                    handle: '.wpsurvey-question-header',
                    placeholder: 'wpsurvey-question-placeholder',
                    update: function(event, ui) {
                        SurveyBuilder.reorderQuestions();
                    }
                });
            }
        },
        
        /**
         * 绑定事件
         */
        bindEvents: function() {
            var self = this;
            
            // 添加题目
            $(document).on('click', '.wpsurvey-btn-add-question', function() {
                var type = $('#wpsurvey-new-question-type').val();
                self.addQuestion(type);
            });
            
            // 删除题目
            $(document).on('click', '.wpsurvey-btn-delete-question', function() {
                if (confirm(wpsurvey_admin.strings.confirm_delete_question)) {
                    var $item = $(this).closest('.wpsurvey-question-item');
                    // 立即从 DOM 中移除，避免 fadeOut 期间的数据混乱
                    $item.remove();
                    SurveyBuilder.reorderQuestions();
                }
            });
            
            // 添加选项
            $(document).on('click', '.wpsurvey-btn-add-option', function() {
                var $list = $(this).siblings('.wpsurvey-options-list');
                var $questionItem = $(this).closest('.wpsurvey-question-item');
                var index = $questionItem.data('index');
                var optionCount = $list.find('.wpsurvey-option-item').length;
                
                var html = '<div class="wpsurvey-option-item">' +
                    '<input type="hidden" name="questions[' + index + '][option_ids][]" value="0">' +
                    '<input type="text" name="questions[' + index + '][options][]" placeholder="选项内容">' +
                    '<select name="questions[' + index + '][jump_to][]" class="wpsurvey-jump-select" title="选择此项后跳转到">' +
                    '<option value="">默认顺序</option>' +
                    '<option value="0">结束问卷</option>' +
                    '</select>' +
                    '<button type="button" class="wpsurvey-btn-delete-option"><span class="dashicons dashicons-no"></span></button>' +
                    '</div>';
                
                $list.append(html);
            });
            
            // 删除选项
            $(document).on('click', '.wpsurvey-btn-delete-option', function() {
                var $list = $(this).closest('.wpsurvey-options-list');
                if ($list.find('.wpsurvey-option-item').length > 1) {
                    $(this).closest('.wpsurvey-option-item').remove();
                    // 删除后重新验证所有题目的选项索引，确保 options 和 jump_to 一致
                    SurveyBuilder.validateOptionArrays($list.closest('.wpsurvey-question-item'));
                } else {
                    $(this).closest('.wpsurvey-option-item').find('input').val('');
                }
            });
            
            // 验证选项数组一致性：确保每个题目的 options[] 和 jump_to[] 长度相同
            // 同时确保每个 option-item 都包含完整的 input + select + delete button
            SurveyBuilder.validateOptionArrays = function($questionItem) {
                if (!$questionItem) {
                    $questionItem = $('.wpsurvey-question-item');
                }
                $questionItem.each(function() {
                    var $q = $(this);
                    var index = $q.data('index');
                    var $options = $q.find('.wpsurvey-option-item');
                    
                    $options.each(function(optIdx) {
                        var $item = $(this);
                        
                        // 确保 option_id hidden 字段存在
                        var $optId = $item.find('input[name$="[option_ids][]"]');
                        if (!$optId.length) {
                            $item.prepend('<input type="hidden" name="questions[' + index + '][option_ids][]" value="0">');
                        }
                        
                        // 确保 input 存在
                        var $input = $item.find('input[name$="[options][]"]');
                        if (!$input.length) {
                            $item.prepend('<input type="text" name="questions[' + index + '][options][]" placeholder="选项内容">');
                        }
                        
                        // 确保 select 存在
                        var $select = $item.find('select[name$="[jump_to][]"]');
                        if (!$select.length) {
                            var selectHtml = '<select name="questions[' + index + '][jump_to][]" class="wpsurvey-jump-select" title="选择此项后跳转到">' +
                                '<option value="">默认顺序</option>' +
                                '<option value="0">结束问卷</option>' +
                                '</select>';
                            $item.append(selectHtml);
                        }
                        
                        // 确保 delete 按钮存在
                        var $deleteBtn = $item.find('.wpsurvey-btn-delete-option');
                        if (!$deleteBtn.length) {
                            $item.append('<button type="button" class="wpsurvey-btn-delete-option"><span class="dashicons dashicons-no"></span></button>');
                        }
                    });
                });
            };
            
            // 题型切换
            $(document).on('change', '.wpsurvey-question-type-select', function() {
                var $item = $(this).closest('.wpsurvey-question-item');
                var index = $item.data('index');
                var newType = $(this).val();
                
                self.updateQuestionType($item, index, newType);
            });
            
            // 删除问卷（AJAX）
            $(document).on('click', '.wpsurvey-delete-survey', function(e) {
                e.preventDefault();
                var $btn = $(this);
                
                if (!confirm(wpsurvey_admin.strings.confirm_delete)) {
                    return;
                }
                
                $.ajax({
                    url: wpsurvey_admin.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'wpsurvey_delete_survey',
                        survey_id: $btn.data('id'),
                        nonce: wpsurvey_admin.nonce
                    },
                    beforeSend: function() {
                        $btn.prop('disabled', true);
                    },
                    success: function(response) {
                        if (response.success) {
                            $btn.closest('tr').fadeOut(200, function() {
                                $(this).remove();
                            });
                        } else {
                            alert(response.data.message || '删除失败');
                            $btn.prop('disabled', false);
                        }
                    },
                    error: function() {
                        alert('请求失败，请重试');
                        $btn.prop('disabled', false);
                    }
                });
            });
            
            // 复制问卷（AJAX）
            $(document).on('click', '.wpsurvey-duplicate-survey', function(e) {
                e.preventDefault();
                var $btn = $(this);
                
                $.ajax({
                    url: wpsurvey_admin.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'wpsurvey_duplicate_survey',
                        survey_id: $btn.data('id'),
                        nonce: wpsurvey_admin.nonce
                    },
                    beforeSend: function() {
                        $btn.prop('disabled', true);
                    },
                    success: function(response) {
                        if (response.success) {
                            window.location.href = response.data.edit_url;
                        } else {
                            alert(response.data.message || '复制失败');
                            $btn.prop('disabled', false);
                        }
                    },
                    error: function() {
                        alert('请求失败，请重试');
                        $btn.prop('disabled', false);
                    }
                });
            });
            
            // 复制短代码
            $(document).on('click', '.btn-copy-shortcode', function() {
                var $input = $(this).siblings('input');
                $input.select();
                document.execCommand('copy');
                
                var $btn = $(this);
                var originalText = $btn.text();
                $btn.text('已复制');
                setTimeout(function() {
                    $btn.text(originalText);
                }, 1500);
            });
        },
        
        /**
         * 添加题目
         */
        addQuestion: function(type) {
            var self = this;
            var $builder = $('.wpsurvey-questions-builder');
            var $panelHeader = $('.wpsurvey-panel[data-type="questions"] .wpsurvey-panel-header, .wpsurvey-questions-builder').closest('.wpsurvey-panel').find('.wpsurvey-panel-header');
            var $addBtn = $panelHeader.find('.wpsurvey-btn-add-question');

            // 防止重复点击
            if ($addBtn.prop('disabled')) return;
            $addBtn.prop('disabled', true).addClass('processing');

            // 获取新的索引
            var $items = $builder.find('.wpsurvey-question-item');
            var index = $items.length;

            $.ajax({
                url: wpsurvey_admin.ajax_url,
                type: 'POST',
                data: {
                    action: 'wpsurvey_get_question_form',
                    question_type: type,
                    question_id: 0,
                    index: index,
                    nonce: wpsurvey_admin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        $builder.append(response.data.html);
                        $builder.addClass('has-questions');
                        $('.wpsurvey-edit-main').addClass('has-questions');
                        
                        // 添加新题目后，重新排序所有题目以确保索引连续
                        self.reorderQuestions();
                        
                        self.loadJumpTargets();

                        // 隐藏悬浮按钮
                        
                        // 滚动回"添加题目"按钮位置，保持按钮在视野内
                        if ($addBtn.length) {
                            $('html, body').animate({
                                scrollTop: Math.max(0, $addBtn.offset().top - 100)
                            }, 300);
                        }
                    }
                },
                complete: function() {
                    $addBtn.prop('disabled', false).removeClass('processing');
                    var floatingBtn = jQuery("#wpsurvey-floating-add-btn");
                    if (builder.find(".wpsurvey-question-item").length >= 3) {
                        floatingBtn.show();
                    } else {
                        floatingBtn.hide();
                    }
                }
            });
        },
        
        /**
         * 更新题目类型显示
         */
        updateQuestionType: function($item, index, newType) {
            var $body = $item.find('.wpsurvey-question-body');
            var $table = $body.find('.wpsurvey-form-table');
            // 在整个 question-body 中查找 id 字段
            var $idField = $body.find('input[type="hidden"][name$="[id]"]');
            var questionId = $idField.val() || 0;
            var typeLabels = {
                'radio': '单选题',
                'checkbox': '多选题',
                'select': '下拉选择',
                'text': '单行文本',
                'textarea': '多行文本',
                'rating': '评分题',
                'matrix': '矩阵题'
            };
            
            // 更新类型标签
            $item.find('.wpsurvey-question-type').text(typeLabels[newType] || newType);
            
            // 移除所有特定行（选项、评分、矩阵）
            $body.find('.wpsurvey-options-row, .wpsurvey-rating-row, .wpsurvey-matrix-row').remove();
            
            var optionsHtml = '';
            
            if (newType === 'radio' || newType === 'checkbox' || newType === 'select') {
                optionsHtml = '<tr class="wpsurvey-options-row">' +
                    '<th>选项 <span class="required">*</span></th>' +
                    '<td>' +
                    '<div class="wpsurvey-options-list">' +
                    '<div class="wpsurvey-option-item">' +
                    '<input type="hidden" name="questions[' + index + '][option_ids][]" value="0">' +
                    '<input type="text" name="questions[' + index + '][options][]" placeholder="选项内容">' +
                    '<select name="questions[' + index + '][jump_to][]" class="wpsurvey-jump-select" title="选择此项后跳转到">' +
                    '<option value="">默认顺序</option>' +
                    '<option value="0">结束问卷</option>' +
                    '</select>' +
                    '<button type="button" class="wpsurvey-btn-delete-option"><span class="dashicons dashicons-no"></span></button>' +
                    '</div>' +
                    '</div>' +
                    '<button type="button" class="wpsurvey-btn-add-option button"><span class="dashicons dashicons-plus"></span> 添加选项</button>' +
                    '</td></tr>';
            } else if (newType === 'rating') {
                optionsHtml = '<tr class="wpsurvey-rating-row">' +
                    '<th>最高分值</th>' +
                    '<td>' +
                    '<select name="questions[' + index + '][max_score]">' +
                    '<option value="5">5星 / 5分</option>' +
                    '<option value="10">10星 / 10分</option>' +
                    '</select>' +
                    '</td></tr>';
            } else if (newType === 'matrix') {
                optionsHtml = '<tr class="wpsurvey-matrix-row">' +
                    '<th>矩阵设置</th>' +
                    '<td>' +
                    '<div class="wpsurvey-matrix-rows">' +
                    '<label>行标题（每行一个）：</label>' +
                    '<textarea name="questions[' + index + '][matrix_rows]" rows="3" placeholder="例如：&#10;商品质量&#10;配送速度"></textarea>' +
                    '</div>' +
                    '<div class="wpsurvey-matrix-columns">' +
                    '<label>列标题（每列一个）：</label>' +
                    '<textarea name="questions[' + index + '][matrix_columns]" rows="3" placeholder="例如：&#10;非常满意&#10;满意&#10;一般"></textarea>' +
                    '</div>' +
                    '</td></tr>';
            }
            
            $table.append(optionsHtml);
            
            // 恢复 hidden id 字段 - 只更新 name，不删除重建
            var $body = $item.find('.wpsurvey-question-body');
            var $existingId = $body.find('input[type="hidden"][name$="[id]"]');
            if ($existingId.length) {
                $existingId.attr('name', 'questions[' + index + '][id]');
            } else {
                $body.append('<input type="hidden" name="questions[' + index + '][id]" value="' + questionId + '">');
            }
            
            // 更新跳转选择框
            this.loadJumpTargets();
        },
        
        /**
         * 重新排序题目
         * 关键修复：不删除重建 id 字段，而是直接更新 name 属性，避免数据丢失
         */
        reorderQuestions: function() {
            var $builder = $('.wpsurvey-questions-builder');
            // 只选择可见的题目项，排除正在 fadeOut 的元素
            var $items = $builder.find('.wpsurvey-question-item:visible');
            
            $items.each(function(index) {
                var $item = $(this);
                $item.attr('data-index', index);
                $item.find('.wpsurvey-question-number').text('题目 ' + (index + 1));
                
                // 先清理：每个题目只保留一个正确的 id hidden 字段
                var $body = $item.find('.wpsurvey-question-body');
                var $idFields = $body.find('input[type="hidden"][name$="[id]"]');
                if ($idFields.length > 1) {
                    // 有多个 id 字段，保留第一个，删除其余的
                    $idFields.slice(1).remove();
                }
                
                // 直接更新所有 name 以 questions[ 开头的表单字段
                $item.find('[name^="questions["]').each(function() {
                    var $field = $(this);
                    var oldName = $field.attr('name');
                    // 匹配 questions[数字] 并替换为新的索引
                    var newName = oldName.replace(/^questions\[\d+\]/, 'questions[' + index + ']');
                    $field.attr('name', newName);
                });
            });
            
            this.loadJumpTargets();
        },
        
        /**
         * 更新题目编号
         */
        updateQuestionNumbers: function() {
            var $items = $('.wpsurvey-questions-builder .wpsurvey-question-item');
            
            $items.each(function(index) {
                $(this).find('.wpsurvey-question-number').text('题目 ' + (index + 1));
            });
        },
        
        /**
         * 加载跳转目标选项
         * 每次调用都重新加载，确保新增/删除题目后选项列表保持最新
         */
        loadJumpTargets: function() {
            var surveyId = $('#survey_id').val();
            if (!surveyId) return;

            var $selects = $('.wpsurvey-jump-select');
            if (!$selects.length) return;

            $.ajax({
                url: wpsurvey_admin.ajax_url,
                type: 'POST',
                data: {
                    action: 'wpsurvey_get_jump_targets',
                    survey_id: surveyId,
                    nonce: wpsurvey_admin.nonce
                },
                success: function(response) {
                    if (response.success && response.data.targets) {
                        var currentOptions = {};
                        
                        $selects.each(function() {
                            var currentVal = $(this).val();
                            if (currentVal) {
                                currentOptions[$(this).index()] = currentVal;
                            }
                        });
                        
                        $selects.each(function() {
                            var $select = $(this);
                            var idx = $select.index();
                            
                            // 保留前两个选项（默认和结束）
                            var existingOptions = $select.find('option').slice(0, 2).clone();
                            
                            $select.empty();
                            
                            // 重新添加基础选项
                            existingOptions.each(function() {
                                $select.append($(this));
                            });
                            
                            // 添加跳转目标
                            $.each(response.data.targets, function(i, target) {
                                var $opt = $('<option value="' + target.id + '">' + target.text + '</option>');
                                $select.append($opt);
                            });
                            
                            // 恢复已选值
                            if (currentOptions[idx]) {
                                $select.val(currentOptions[idx]);
                            }
                        });
                    }
                }
            });
        }
    };
    
    // 颜色选择器
    var ColorPicker = {
        init: function() {
            $('input[type="color"]').each(function() {
                var $color = $(this);
                var $text = $color.siblings('input[type="text"]');
                
                // 颜色选择变化时更新文本
                $color.on('input', function() {
                    $text.val($(this).val());
                });
                
                // 文本变化时更新颜色选择器
                $text.on('input', function() {
                    var val = $(this).val();
                    if (/^#[0-9a-fA-F]{6}$/.test(val)) {
                        $color.val(val);
                    }
                });
            });
        }
    };
    
    // 复制到剪贴板功能
    var CopyToClipboard = {
        init: function() {
            var self = this;

            // 点击复制按钮
            $(document).on('click', '.wpsurvey-copy-btn', function(e) {
                e.preventDefault();
                var $btn = $(this);
                var $box = $btn.closest('.wpsurvey-shortcode-box');
                var text = $box.find('code').text();

                self.copy(text, function(success) {
                    if (success) {
                        var originalText = $btn.text();
                        $btn.text('已复制!');
                        $btn.addClass('copied');
                        setTimeout(function() {
                            $btn.text(originalText);
                            $btn.removeClass('copied');
                        }, 1500);
                    }
                });
            });

            // 点击 code 区域直接复制
            $(document).on('click', '.wpsurvey-shortcode-box code', function(e) {
                e.preventDefault();
                var text = $(this).text();
                self.copy(text, function(success) {
                    if (success) {
                        var $box = $(this).closest('.wpsurvey-shortcode-box');
                        var $btn = $box.find('.wpsurvey-copy-btn');
                        $btn.trigger('click');
                    }
                }.bind(this));
            });
        },

        copy: function(text, callback) {
            if (navigator.clipboard && navigator.clipboard.writeText) {
                // 现代浏览器
                navigator.clipboard.writeText(text).then(function() {
                    callback(true);
                }).catch(function() {
                    // Fallback to older method
                    fallbackCopy(text, callback);
                });
            } else {
                // 老旧浏览器回退
                fallbackCopy(text, callback);
            }

            function fallbackCopy(text, callback) {
                var $temp = $('<textarea>');
                $('body').append($temp);
                $temp.val(text).select();
                try {
                    document.execCommand('copy');
                    $temp.remove();
                    callback(true);
                } catch (err) {
                    $temp.remove();
                    callback(false);
                }
            }
        }
    };

    // 文档就绪
    $(document).ready(function() {
        SurveyBuilder.init();
        ColorPicker.init();
        CopyToClipboard.init();
        
        // 表单提交前验证：确保所有题目都有正确的 id 字段
        $('form').on('submit', function(e) {
            var $builder = $('.wpsurvey-questions-builder');
            // 只处理可见的题目，排除正在删除的
            var $items = $builder.find('.wpsurvey-question-item:visible');
            var hasError = false;
            var ids = {};
            var duplicateIds = [];
            
            // 强制重新排序，确保所有索引连续且正确
            SurveyBuilder.reorderQuestions();
            
            // 重新获取排序后的可见题目
            $items = $builder.find('.wpsurvey-question-item:visible');
            
            $items.each(function(domIndex) {
                var $item = $(this);
                var currentIndex = domIndex;
                
                // 更新 data-index
                $item.attr('data-index', currentIndex);
                
                // 查找此题目的 id 字段 - 使用精确匹配
                var $idField = $item.find('input[type="hidden"]').filter(function() {
                    return $(this).attr('name') === 'questions[' + currentIndex + '][id]';
                });
                
                var idValue = '0';
                
                if ($idField.length) {
                    idValue = $idField.val() || '0';
                } else {
                    // 如果没有找到精确匹配的 id 字段，查找任何 id 字段并更新 name
                    var $anyId = $item.find('input[type="hidden"][name$="[id]"]');
                    if ($anyId.length) {
                        idValue = $anyId.val() || '0';
                        $anyId.attr('name', 'questions[' + currentIndex + '][id]');
                    } else {
                        // 如果没有 id 字段，添加一个
                        $item.find('.wpsurvey-question-body').append(
                            '<input type="hidden" name="questions[' + currentIndex + '][id]" value="0">'
                        );
                    }
                }
                
                // 检查重复 ID（排除 0，即新题目）
                if (idValue !== '0' && idValue !== '' && idValue !== 0) {
                    if (ids[idValue]) {
                        duplicateIds.push({
                            id: idValue,
                            index: currentIndex,
                            text: $item.find('textarea[name$="[text]"]').val() || ''
                        });
                        hasError = true;
                    }
                    ids[idValue] = true;
                }
            });
            
            if (hasError) {
                e.preventDefault();
                console.error('重复的题目 ID 详情:', duplicateIds);
                alert('检测到题目数据异常（重复 ID: ' + duplicateIds.map(function(d) { return d.id; }).join(', ') + '），请刷新页面后重试。');
                return false;
            }
        });
        
        // 显示提示消息
        var urlParams = new URLSearchParams(window.location.search);
        var message = urlParams.get('message');
        
        if (message) {
            var messages = {
                'saved': '保存成功',
                'deleted': '删除成功',
                'published': '发布成功',
                'draft': '已设为草稿',
                'duplicate': '复制成功'
            };
            
            if (messages[message]) {
                var $notice = $('<div class="wpsurvey-notice success"><p>' + messages[message] + '</p></div>');
                $('.wpsurvey-admin-wrap h1').after($notice);
                
                setTimeout(function() {
                    $notice.fadeOut();
                }, 3000);
            }
        }
    });
    
})(jQuery);
