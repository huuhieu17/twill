@extends('twill::layouts.form')

@section('contentFields')
    @formField('input', [
        'name' => 'title',
        'label' => 'Tiêu đề',
        'translated' => true,
        'required' => true,
        'note' => 'Tiêu đề cho bài viết',
        'placeholder' => 'Tiêu đề cho bài viết',
        'maxlength' => 200
    ])

    @formField('input', [
    'name' => 'owner',
    'label' => 'Tên người đăng',
    'maxlength' => 200
    ])

    @formField('tags',[
        'label'=>'Tags'
    ])

    @formField('wysiwyg', [
    'name' => 'content',
    'label' => 'Nội dung bài viết',
    'toolbarOptions' => [
    ['header' => [2, 3, 4, 5, 6, false]],
    'bold',
    'italic',
    'underline',
    'strike',
    ["script" => "super"],
    ["script" => "sub"],
    "blockquote",
    "code-block",
    ['list' => 'ordered'],
    ['list' => 'bullet'],
    ['indent' => '-1'],
    ['indent' => '+1'],
    ["align" => []],
    ["direction" => "rtl"],
    'link',
    "clean",
    ],
    'placeholder' => 'Case study text',
    'editSource' => true,
    'note' => 'Hint message`',
    ])

    @formField('medias',[
        'name' => 'cover',
        'label' => 'Ảnh bài viết'
    ])
@stop
@section('fieldsets')
    <a17-fieldset id="cover" title="Website thumbnail" :open="true">
        @formField('input', [
        'name' => 'title',
        'label' => 'Tiêu đề',
        'translated' => true,
        'required' => true,
        'note' => 'Tiêu đề cho bài viết',
        'placeholder' => 'Tiêu đề cho bài viết',
        'maxlength' => 200
        ])

        @formField('input', [
        'name' => 'owner',
        'label' => 'Tên người đăng',
        'maxlength' => 200
        ])

        @formField('tags',[
        'label'=>'Tags'
        ])

        @formField('wysiwyg', [
        'name' => 'content',
        'label' => 'Nội dung bài viết',
        'toolbarOptions' => [
        ['header' => [2, 3, 4, 5, 6, false]],
        'bold',
        'italic',
        'underline',
        'strike',
        ["script" => "super"],
        ["script" => "sub"],
        "blockquote",
        "code-block",
        ['list' => 'ordered'],
        ['list' => 'bullet'],
        ['indent' => '-1'],
        ['indent' => '+1'],
        ["align" => []],
        ["direction" => "rtl"],
        'link',
        "clean",
        ],
        'placeholder' => 'Case study text',
        'editSource' => true,
        'note' => 'Hint message`',
        ])

        @formField('medias',[
        'name' => 'cover',
        'label' => 'Ảnh bài viết'
        ])
    </a17-fieldset>
@endsection
