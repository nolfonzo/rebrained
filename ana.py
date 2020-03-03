def anagrams(wordIn):
    f=open('/usr/share/dict/words')
    ana=dict()
    for word in f.readlines():
        ana.setdefault(''.join(sorted(word.rstrip())),[]).append(word.rstrip())
    return ana.get(''.join(sorted(wordIn)))
