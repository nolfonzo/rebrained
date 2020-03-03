
class Node:
    def __init__(self,data,prev=None,next=None,child=None):
        self.data=data
        self.prev=prev
        self.next=next
        self.child=child

class ListFlattener:
    “””
    >>> node1=Node(1)
    >>> node5=Node(5)
    >>> node1.child=node5
    >>> node2=Node(2)
    >>> node3=Node(3,node1,None,node2)
    >>> node1.next=node3
    >>> node4=Node(4,node2)
    >>> node2.next=node4
    >>> ListFlattener.listAsArray(node1)
    [1, 3]
    >>> ListFlattener.flattenList(node1)
    >>> ListFlattener.listAsArray(node1)
    [1, 3, 5, 2, 4]
    >>> ListFlattener.unflattenList(node1)
    >>> ListFlattener.listAsArray(node1)
    [1, 3]
    >>> ListFlattener.flattenList(node1)
    >>> ListFlattener.listAsArray(node1)
    [1, 3, 5, 2, 4]
    >>> ListFlattener.listAsArray(ListFlattener.findTail(node1),False)
    [4, 2, 5, 3, 1]
    """
    @staticmethod
    def flattenList(head):
        curr=head
        tail=ListFlattener.findTail(head)
        while curr!=None:
            if curr.child!=None:
                tail=ListFlattener.appendChildToEnd(curr,curr.child,tail)
            curr=curr.next

    @staticmethod
    def unflattenList(head):
        curr=head
        while curr!=None:
            if curr.child!=None:
                curr.child.prev.next=None
                curr.child.prev=None
                ListFlattener.unflattenList(curr.child)
            curr=curr.next

    @staticmethod
    def appendChildToEnd(node,child,tail):
        tail.next=child
        child.prev=tail
        return ListFlattener.findTail(child)

    @staticmethod
    def findTail(node):
        while node.next!=None:
            node=node.next
        return node

    @staticmethod
    def listAsArray(node,forward=True):
        list=[]
        while node!=None:
            list.append(node.data)
            if forward:
                node=node.next
            else:
                node=node.prev
        return list

if __name__ == "__main__":
    import doctest
    doctest.testmod()
:set nonumber
