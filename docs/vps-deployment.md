# نشر Sigma من المستودع

مستودع الموقع المعتمد هو remote `vps`: `sigma-eg@187.77.66.167:/home/sigma-eg/git/sigma-site.git`، وفرع النشر `main`. رفع commit إلى هذا الفرع يشغّل `post-receive` وينشره تلقائيًا. استخدم `publish-vps.ps1` للإصدارات المعتادة؛ بعد نجاح النشر والتحقق من الموقع، يرفع **نفس commit** تلقائيًا إلى فرع `production` في GitHub `ammar7rb/bahaa` ويتحقق من SHA. يرفض السكربت الإصدار مسبقًا إذا لم يتطابق GitHub `production` مع VPS `main`، حتى لا تتراكم نسخ مختلفة.

اعمل على أحدث نسخة من main في worktree منفصل. لا تنسخ ملفات محلية قديمة فوق أحدث ملفات الخادم. حدد ملفات التعديل فقط بعد اختبارها:

```powershell
$env:GIT_SSH_COMMAND='ssh -i C:/Users/Hosopa/.ssh/sigma_site_git_push -o IdentitiesOnly=yes -o BatchMode=yes'
git fetch vps main
$base=git rev-parse FETCH_HEAD
.\publish-vps.ps1 -ExpectedBase $base -Paths @('public/assets/back-end/js/customer-accounts.js') -Message 'Fix customer profile scrolling'
```

السكربت ينشئ commit فوق أساس main باستخدام index منفصل دون ضم جميع تعديلات مساحة العمل أو تغيير index الحالي. يرفض تغير الأساس والملفات المحمية، ويفحص PHP والـdiff، ويرفع إلى المستودع. لا يعلن النجاح إلا بعد تطابق .deploy-current مع الـcommit ونجاح فحص API ووجود SHA نفسه في GitHub `production`.

فرع GitHub `main` له تاريخ مستقل وقديم؛ لا يحدّثه هذا المسار ولا يستخدم force push. فرع `production` هو النسخة المطابقة للخادم للمراجعة والتتبع. الرفع المباشر إلى VPS خارج `publish-vps.ps1` ينشر على الموقع، لكنه لا يحدّث GitHub تلقائيًا؛ عندئذٍ يجب مصالحة الفرعين قبل الإصدار التالي.

## ما يجري تلقائيًا على الخادم

1. قفل النشر والتحقق من أن الموقع ليس في صيانة قائمة.
2. مطابقة المصدر الحالي مع الـcommit المنشور؛ أي تعديل مباشر خارج Git يوقف النشر ويحتاج مصالحة معلنة.
3. فحص المسارات والأرشيف وصياغة PHP وحفظ نسخة كود احتياطية خاصة.
4. صيانة قصيرة وتثبيت الملفات المتغيرة فقط، ومسح كاش Laravel وبناء كاش القوالب وإعادة تشغيل عمال الطابور.
5. مطابقة المصدر الجديد بالكامل وفحص الرئيسية وAPI، ثم تحديث مؤشر النسخة وحفظ نتيجة النشر. عند الفشل يُستعاد الكود السابق ويخرج الموقع من الصيانة.

المنفّذ scripts/vps/deploy-source.py يأتي من نفس commit المرفوع. تعريف hook محفوظ في scripts/post-receive-vps ونسخته المثبتة في مستودع الخادم. السجلات والنسخ الاحتياطية خاصة تحت /home/sigma-eg/git، خارج الويب.

.env ومفاتيح التوقيع وبيانات storage والمرفقات وvendor وملفات التشغيل لا تُنسخ من جهاز المطور ولا تضاف إلى المستودع. تغييرات migrations وComposer تتوقف عن مسار النشر المعتاد وتحتاج إصدارًا مستقلًا مفحوصًا؛ لا تُشغل migrations تلقائيًا مع كل push.

عند فشل النشر راجع source-deploy-<commit>.log و.deploy-current. لا تفترض أن main أصبح منشورًا، ولا تستخدم force push أو النسخ الشامل لتجاوز اختلاف المصدر.
